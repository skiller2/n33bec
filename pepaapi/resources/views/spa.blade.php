<!DOCTYPE html>

<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>Laravel 5 / AngularJS JWT example</title>

    <link href="<?= asset('../node_modules/bootstrap/dist/css/bootstrap.min.css') ?>" rel="stylesheet">
    <link href="<?= asset('../node_modules/bootstrap/dist/css/bootstrap-theme.min.css') ?>" rel="stylesheet">
    <link href="<?= asset('css/bootstrap.superhero.min.css') ?>" rel="stylesheet">
    <link rel="stylesheet" type="text/css" href="<?= asset('../node_modules/angular-ui-grid/ui-grid.css') ?>" />
    <link rel="stylesheet" type="text/css" href="<?= asset('../node_modules/angular-ui-grid/ui-grid.min.css') ?>" />
    <link rel="stylesheet" type="text/css" href="<?= asset('../node_modules/angular-ui-bootstrap/dist/ui-bootstrap-csp.css') ?>" />
    <link rel="stylesheet" href="<?= asset('../node_modules/angular-loading-bar/src/loading-bar.css') ?>">
    <link rel="stylesheet" href="{{ asset('/css/app.css') }}">

    <!-- HTML5 shim and Respond.js IE8 support of HTML5 elements and media queries -->
    <!--[if lt IE 9]>
    <script src="https://oss.maxcdn.com/html5shiv/3.7.2/html5shiv.min.js"></script>
    <script src="https://oss.maxcdn.com/respond/1.4.2/respond.min.js"></script>
    <![endif]-->
</head>

<body ng-app="app">
    <div class="navbar navbar-inverse navbar-fixed-top" role="navigation" data-ng-controller="HomeController">
        <div class="container">
            <div class="navbar-header">
                <button type="button" class="navbar-toggle collapsed" data-toggle="collapse" data-target=".navbar-collapse">
                    <span class="sr-only">Toggle navigation</span>
                    <span class="icon-bar"></span>
                    <span class="icon-bar"></span>
                    <span class="icon-bar"></span>
                </button>
                <a class="navbar-brand" href="#/">JWT Angular example</a>
            </div>
            <div class="navbar-collapse collapse">
                <ul class="nav navbar-nav navbar-right">
                    <li data-ng-show="token"><a ng-href="#/restricted">Restricted area</a></li>
                    <li data-ng-show="token"><a ng-href="#/personas">Personas</a></li>
                    <li data-ng-hide="token"><a ng-href="#/signin">Ingresar</a></li>
                    <li data-ng-hide="token"><a ng-href="#/signup">Registrarse</a></li>
                    <li data-ng-show="token"><a ng-href="#/" ng-click="logout()">Logout</a></li>
                </ul>
            </div>
        </div>
    </div>
    <div class="container" ui-view=""></div>
    <div class="footer">
        <div class="container">
            <p class="muted credit">Example by Pepe</p>
        </div>
    </div>

    <!-- Angular References -->
    <script src="<?= asset('app/lib/angular/angular.min.js') ?>"></script>
    <script src="<?= asset('../node_modules/angular-touch/angular-touch.js') ?>"></script>
    <script src="<?= asset('../node_modules/angular-animate/angular-animate.js') ?>"></script>
    <script src="<?= asset('../node_modules/angular-route/angular-route.js') ?>"></script>
    <script src="<?= asset('../node_modules/angular-loading-bar/src/loading-bar.js') ?>"></script>
    <script src="<?= asset('../node_modules/angular-ui-router/release/angular-ui-router.min.js') ?>"></script>
    <script src="<?= asset('../node_modules/ngstorage/ngStorage.js') ?>"></script>
    <script src="<?= asset('app/app.js') ?>"></script>

    <!-- UI References -->
    <script src="<?= asset('../node_modules/angular-ui-grid/ui-grid.js') ?>"></script>
    <script src="<?= asset('../node_modules/angular-ui-grid/ui-grid.min.js') ?>"></script>
    <script src="<?= asset('../node_modules/angular-ui-bootstrap/dist/ui-bootstrap-tpls.js') ?>"></script>
    <script src="<?= asset('../node_modules/angular-ui-bootstrap/dist/ui-bootstrap.js') ?>"></script>
    <script src="<?= asset('../node_modules/angular-ui-router-uib-modal/angular-ui-router-uib-modal.js') ?>"></script>

    <!-- Load Javascript Libraries (AngularJS, JQuery, Bootstrap) -->
    <script src="<?= asset('js/jquery.min.js') ?>"></script>
    <script src="<?= asset('../node_modules/bootstrap/dist/js/bootstrap.min.js') ?>"></script>

    <!-- AngularJS Application Scripts -->
    <script src="<?= asset('app/controllers/personas.js') ?>"></script>
    <script src="<?= asset('app/controllers/home.js') ?>"></script>
    <script src="<?= asset('app/controllers/restricted.js') ?>"></script>
    <script src="<?= asset('app/services/auth.js') ?>"></script>
    <script src="<?= asset('app/services/data.js') ?>"></script>

</body>
</html>