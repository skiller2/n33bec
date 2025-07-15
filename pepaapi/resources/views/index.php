<!DOCTYPE html>
<html lang="en-US" ng-app="uigrid">
    <head>
        <title>Alta Personas</title>

        <!-- Load Bootstrap CSS -->
        <link href="<?= asset('css/bootstrap.min.css') ?>" rel="stylesheet">
        <script src="<?= asset('../node_modules/modernizr-2.6.2.js') ?>"></script>
        <link rel="stylesheet" type="text/css" href="../node_modules/angular-ui-grid/ui-grid.css" />
        <link rel="stylesheet" type="text/css" href="../node_modules/angular-ui-grid/ui-grid.min.css" />
        <link rel="stylesheet" type="text/css" href="../node_modules/font-awesome/css/font-awesome.css" />
    </head>
    <body>
        <h2>Personas</h2>

        <div  ng-controller="MainCtrl">
            <div ui-grid="gridOptions"
                 ui-grid-resize-columns
                 ui-grid-move-columns
                 ui-grid-exporter
                 ui-grid-selection
                 ui-grid-pinning style="width: 100%;height: 250px;"></div>
            <p>Current page:
                <input type="number" min="1" max="{{ pagination.getTotalPages() }}" ng-model="pagination.pageNumber" ng-change="load()">of {{ pagination.getTotalPages() }}</p>
            <button type="button" class="btn btn-success" ng-click="pagination.firstPage()">first page</button>
            <button type="button" class="btn btn-success" ng-click="pagination.previousPage()">previous page</button>
            <button type="button" class="btn btn-success" ng-click="pagination.nextPage()">next page</button>
            <button type="button" class="btn btn-success" ng-click="pagination.lastPage()">last page</button>
        </div>


        <!-- Angular References -->
        <script src="<?= asset('app/lib/angular/angular.min.js') ?>"></script>
        <script src="<?= asset('../node_modules/angular-touch/angular-touch.js') ?>"></script>
        <script src="<?= asset('../node_modules/angular-animate/angular-animate.js') ?>"></script>
        <script src="<?= asset('../node_modules/angular-route/angular-route.js') ?>"></script>

        <!-- UI References -->
        <script src="<?= asset('../node_modules/angular-ui-grid/ui-grid.js') ?>"></script>
        <script src="<?= asset('../node_modules/angular-ui-grid/ui-grid.min.js') ?>"></script>

        <!-- Load Javascript Libraries (AngularJS, JQuery, Bootstrap) -->
        <script src="<?= asset('js/jquery.min.js') ?>"></script>
        <script src="<?= asset('js/bootstrap.min.js') ?>"></script>

        <!-- AngularJS Application Scripts -->
        <script src="<?= asset('app/app.js') ?>"></script>
        <script src="<?= asset('app/controllers/personas.js') ?>"></script>

    </body>
</html>
