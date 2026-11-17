<?= $this->extend(config('Auth')->views['layout']) ?>

<?= $this->section('title') ?>Autorizar acesso <?= $this->endSection() ?>

<?= $this->section('main') ?>

    <div class="container d-flex justify-content-center p-5">
        <div class="card col-12 col-md-6 shadow-sm">
            <div class="card-body">
                <img src="<?= shared_asset('assets/img/logo-login.png') ?>" alt="Logo" class="img-fluid mb-4" style="max-height: 60px;">

                <h5 class="card-title mb-3">Autorizar acesso de IA externa</h5>

                <p class="mb-1">
                    <strong><?= esc($client['client_name'] ?: 'Um cliente MCP externo') ?></strong>
                    quer se conectar ao sistema em nome de
                    <strong><?= esc($usuario->email ?? $usuario->username ?? '') ?></strong>.
                </p>

                <p class="text-muted" style="font-size: 0.9rem;">
                    A partir daí, ele poderá agir estritamente como você dentro do sistema — respeitando
                    suas permissões — através do endpoint MCP (escopo <code><?= esc($escopo) ?></code>).
                    Você pode revogar esse acesso a qualquer momento em
                    <em>Configurações &rsaquo; Integração IA (MCP)</em>.
                </p>

                <form action="<?= site_url('oauth/authorize') ?>" method="post" class="mt-4">
                    <?= csrf_field() ?>
                    <input type="hidden" name="client_id" value="<?= esc($clientId) ?>">
                    <input type="hidden" name="redirect_uri" value="<?= esc($redirectUri) ?>">
                    <input type="hidden" name="state" value="<?= esc($state ?? '') ?>">
                    <input type="hidden" name="code_challenge" value="<?= esc($codeChallenge) ?>">
                    <input type="hidden" name="code_challenge_method" value="<?= esc($codeChallengeMethod) ?>">

                    <div class="d-flex gap-2 justify-content-end mt-3">
                        <button type="submit" name="decisao" value="negar" class="btn btn-outline-secondary">
                            Negar
                        </button>
                        <button type="submit" name="decisao" value="permitir" class="btn btn-primary">
                            Permitir acesso
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

<?= $this->endSection() ?>
