<?php

declare(strict_types=1);

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use CodeIgniter\Shield\Entities\User;
use Throwable;

/**
 * Instalação inicial: roda as migrations, carrega os cadastros-base e cria o
 * primeiro usuário (superadmin), deixando o sistema pronto para cadastrar
 * usuários e clientes.
 *
 * Idempotente: pode ser executado de novo sem duplicar cadastros-base nem criar
 * um segundo superadmin com o mesmo e-mail.
 */
class Instalar extends BaseCommand
{
    protected $group       = 'Sistema';
    protected $name        = 'app:instalar';
    protected $description = 'Roda as migrations, carrega os cadastros-base e cria o superadmin inicial.';
    protected $usage       = 'app:instalar [--email EMAIL] [--usuario USUARIO] [--senha SENHA]';
    protected $options     = [
        '--email'   => 'E-mail do superadmin (pergunta se omitido).',
        '--usuario' => 'Nome de usuário do superadmin (padrão: parte do e-mail antes do @).',
        '--senha'   => 'Senha do superadmin (pergunta se omitido; mínimo de 8 caracteres).',
    ];

    public function run(array $params)
    {
        $email   = (string) (CLI::getOption('email') ?: CLI::prompt('E-mail do superadmin', null, 'required|valid_email'));
        $usuario = (string) (CLI::getOption('usuario') ?: explode('@', $email)[0]);
        $senha   = (string) (CLI::getOption('senha') ?: CLI::prompt('Senha do superadmin (mín. 8 caracteres)', null, 'required|min_length[8]'));

        if (strlen($senha) < 8) {
            CLI::error('A senha precisa ter ao menos 8 caracteres.');

            return EXIT_ERROR;
        }

        try {
            CLI::write('1/3 Rodando migrations...', 'yellow');
            command('migrate');
            command('contabil:migrate');
            cache()->clean(); // descarta números de uma instalação anterior

            CLI::write('2/3 Carregando cadastros-base...', 'yellow');
            $this->semear('ModulosSeeder');
            $this->semearSeVazia('fin_categorias', 'FinCategoriasSeeder');
            $this->semearSeVazia('fin_elementos', 'FinElementosSeeder');
            $this->semearSeVazia('crm_etapas', 'CrmSeeder');
            $this->semearSeVazia('acc_entities', 'ContabilSeeder');

            CLI::write('3/3 Criando o superadmin...', 'yellow');
            $this->criarSuperadmin($email, $usuario, $senha);
        } catch (Throwable $e) {
            CLI::error('Falha na instalação: ' . $e->getMessage());

            return EXIT_ERROR;
        }

        CLI::write('Instalação concluída. Entre com o e-mail e a senha informados.', 'green');

        return EXIT_SUCCESS;
    }

    private function semear(string $seeder): void
    {
        \Config\Database::seeder()->call($seeder);
    }

    private function semearSeVazia(string $tabela, string $seeder): void
    {
        if (db_connect()->table($tabela)->countAllResults() === 0) {
            $this->semear($seeder);
        }
    }

    private function criarSuperadmin(string $email, string $usuario, string $senha): void
    {
        $users = auth()->getProvider();

        if ($users->findByCredentials(['email' => $email]) !== null) {
            CLI::write("Já existe um usuário com o e-mail {$email}; nada foi criado.", 'yellow');

            return;
        }

        $user = new User(['username' => $usuario, 'email' => $email, 'password' => $senha]);
        $users->save($user);

        $user = $users->findById($users->getInsertID());
        $user->activate();
        $user->addGroup('superadmin');
        db_connect()->table('users')->where('id', $user->id)->update(['active' => 1]);

        CLI::write("Superadmin criado: {$usuario} <{$email}>", 'green');
    }
}
