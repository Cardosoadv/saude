<div id="toast-container" class="position-fixed top-0 end-0 p-5" style="z-index: 11"></div>

<!--begin::Footer-->
<footer class="app-footer d-flex justify-content-between align-items-center">
    <div class="text-start">
        <strong>
            Copyright &copy; 2026&nbsp;
            <a href="http://cognitiotecnologia.com.br/" class="text-decoration-none">Cognitio Tecny</a>.
        </strong>
        All rights reserved.
    </div>

    <div class="text-end d-none d-sm-block">
        <a href="https://ordojuris.com.br" class="text-decoration-none">Ordo Juris</a> - v 5.0.0 Beta
    </div>

    <div class="text-center d-none d-sm-block">
        Powered by <a href="https://adminlte.io" class="text-decoration-none">AdminLTE.io</a>
    </div>


</footer>
<!--end::Footer-->

</div><!--end::App Wrapper-->

<!--begin::Scripts com Cache Otimizado-->

<!--begin::Third Party Plugins-->
<script src="https://cdn.jsdelivr.net/npm/overlayscrollbars@2.3.0/browser/overlayscrollbars.browser.es6.min.js" integrity="sha256-H2VM7BKda+v2Z4+DRy69uknwxjyDRhszjXFhsL4gD3w=" crossorigin="anonymous"></script>

<!--begin::Required Plugins-->
<script src="https://code.jquery.com/jquery-3.7.0.min.js" crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js" crossorigin="anonymous"></script>
<script src="https://code.jquery.com/ui/1.13.2/jquery-ui.min.js" crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.min.js" integrity="sha256-YMa+wAM6QkVyz999odX7lPRxkoYAan8suedu4k2Zur8=" crossorigin="anonymous"></script>

<!--begin::AdminLTE com Versioning-->
<script src="<?= shared_asset('dist/js/adminlte.min.js', true) ?>"></script>



<!-- ── AppConfig: injetado pelo PHP antes de qualquer módulo JS ──────── -->
    <?= js_app_config([
        'criticalResources' => [
            shared_asset('dist/js/adminlte.min.js', true),
            shared_asset('dist/css/adminlte-min.css', true),
            shared_asset('dist/css/style-min.css', true)
        ],
        'preloadUrls' => [
            // base_url('api/dados-frequentes')
        ]
    ]) ?>
    <!--
        Você pode passar configurações extras por controller:
        <?= js_app_config(['perfilId' => user_id()]) ?>
    -->
 
    <!-- ── Core + Utils: sempre carregados em todas as páginas ──────────── -->
    <?= js_global() ?>
 
    <!-- ── Módulos: carregados apenas nas views que precisam ─────────────── -->
    <?= js_modules(isset($js_modules) ? $js_modules : []) ?>


<!--begin::Chart Libraries-->
<script src="https://cdn.jsdelivr.net/npm/apexcharts@3.37.1/dist/apexcharts.min.js" integrity="sha256-+vh8GkaU7C9/wbSLIcwq82tQ2wTf44aOHA8HlBMwRI8=" crossorigin="anonymous"></script>

<!--begin::Select2-->
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<!--begin::Notifications-->
<script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js" crossorigin="anonymous"></script>

<!--begin::Performance Monitor (Desenvolvimento)-->
<?php if (ENVIRONMENT === 'development'): ?>
    <script>
        <?php
        // Inclui o Performance Monitor apenas em desenvolvimento
        $perfMonitorPath = shared_asset('js/performance-monitor.js', true);
        if (file_exists($perfMonitorPath)) {
            echo file_get_contents($perfMonitorPath);
        }
        ?>
    </script>
<?php endif; ?>

