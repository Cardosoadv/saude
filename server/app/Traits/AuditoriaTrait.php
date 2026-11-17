<?php

declare(strict_types=1);

namespace App\Traits;

use App\Models\AuditoriaModel;

/**
 * Trait para reutilizar lógica de auditoria em Models.
 */
trait AuditoriaTrait
{
    protected ?AuditoriaModel $auditoriaModelInstance = null;
    protected ?string $ipAddress = null;

    /**
     * Inicializa dependências de auditoria se necessário.
     */
    protected function initAuditoria(): void
    {
        if ($this->auditoriaModelInstance === null) {
            $this->auditoriaModelInstance = new AuditoriaModel();
        }
        if ($this->ipAddress === null) {
            $this->ipAddress = service('request')->getIPAddress();
        }
    }

    /**
     * Callback para registrar auditoria antes da criação (apenas getID no after).
     * Nota: Auditoria de create geralmente é feita no afterInsert para ter o ID.
     */
    protected function auditoriaNovo(array $data): array
    {
        $this->initAuditoria();

        $id = $data['id'] ?? $this->getInsertID();

        $this->auditoriaModelInstance->insert([
            'user_id'     => user_id(),
            'table_name'  => $this->table,
            'action_type' => 'CREATE',
            'dados_novos' => json_encode($data['data'] ?? $data),
            'ip_address'  => $this->ipAddress,
            'created_at'  => date('Y-m-d H:i:s'),
        ]);

        return $data;
    }

    /**
     * Callback para registrar auditoria antes da atualização.
     */
    protected function auditoriaAtualizar(array $data): array
    {
        // Se não houver dados de alteração, retorna
        if (!isset($data['data']) || empty($data['data'])) {
            return $data;
        }

        // Filtra campos que não devem disparar auditoria (ex: last_active do Shield)
        // Isso evita que cada request gere um log de auditoria desnecessário
        $camposIgnorar = ['last_active', 'updated_at'];
        $camposAlterados = array_keys($data['data']);
        $camposRelevantes = array_diff($camposAlterados, $camposIgnorar);

        if (empty($camposRelevantes)) {
            return $data;
        }

        $this->initAuditoria();

        // Verifica se há ID para buscar dados antigos
        if (!isset($data['id'])) {
            return $data;
        }

        $ids = is_array($data['id']) ? $data['id'] : [$data['id']];

        $dadosAntigosLista = $this->whereIn($this->primaryKey, $ids)->findAll();

        $auditLogs = [];
        foreach ($dadosAntigosLista as $dadosAntigos) {
            $auditLogs[] = [
                'user_id'       => user_id(),
                'table_name'    => $this->table,
                'action_type'   => 'UPDATE',
                'dados_antigos' => json_encode($dadosAntigos),
                'dados_novos'   => json_encode($data['data']),
                'ip_address'    => $this->ipAddress,
                'created_at'    => date('Y-m-d H:i:s'),
            ];
        }

        if (!empty($auditLogs)) {
            $this->auditoriaModelInstance->insertBatch($auditLogs);
        }

        return $data;
    }

    /**
     * Callback para registrar auditoria antes da exclusão.
     */
    protected function auditoriaDeletar(array $data): array
    {
        $this->initAuditoria();

        if (isset($data['id'])) {
            $ids = is_array($data['id']) ? $data['id'] : [$data['id']];

            $dadosAntigosLista = $this->whereIn($this->primaryKey, $ids)->findAll();

            $auditLogs = [];
            foreach ($dadosAntigosLista as $dadosAntigos) {
                $auditLogs[] = [
                    'user_id'       => user_id(),
                    'table_name'    => $this->table,
                    'action_type'   => 'DELETE',
                    'dados_antigos' => json_encode($dadosAntigos),
                    'ip_address'    => $this->ipAddress,
                    'created_at'    => date('Y-m-d H:i:s'),
                ];
            }

            if (!empty($auditLogs)) {
                $this->auditoriaModelInstance->insertBatch($auditLogs);
            }
        }

        return $data;
    }
}
