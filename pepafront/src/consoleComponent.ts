'use strict';
import angular from "angular";
const appConsoleComponent = {
    template: require('./Pages/Templates/console_template.html'),
    controllerAs: "console",
    //    bindings: {},
    controller: ConsoleController
};


ConsoleController.$inject = ['$scope', '$filter', '$sce', 'realTimeData', '$timeout'];
function ConsoleController($scope, $filter, $sce, realTimeData, $timeout) {

    const vm = this;
    const highlighted = {};
    let lineIdCounter = 0;
    let timeout;
    vm.connected = false;
    const maxLines = 45;
    let hidebtncnt = 0;
    vm.loglines = [];
    vm.applocal = false;
    const level_icon = {
        debug: 'far fa-check-circle', info: 'far fa-check-circle', notice: 'far fa-check-circle', warning: 'fas fa-exclamation-circle',
        error: 'fas fa-times-circle', critical: 'fas fa-times-circle', alert: 'fas fa-times-circle', emergency: 'fas fa-times-circle',
        primary: 'far fa-question-circle', secondary: 'far fa-check-circle', success: 'far fa-check-circle', light: 'far fa-check-circle', dark: 'fas fa-exclamation-circle', danger: 'fas fa-times-circle',
        alarmatec: 'fas fa-wrench', falla: 'fas fa-exclamation-triangle', prealarma: 'fas fa-bell', alarma: 'fas fa-bell', desconexion: 'fas fa-wrench',
        AT: 'fas fa-wrench', FA: 'fas fa-exclamation-triangle', PA: 'fas fa-bell', AL: 'fas fa-bell', NO: 'far fa-check-circle', DE: 'fas fa-wrench', EV: 'far fa-check-circle',
    };
    const level_class = {
        debug: 'light', info: 'info', notice: 'info', warning: 'warning', error: 'danger', critical: 'danger', alert: 'warning', emergency: 'danger',
        primary: 'primary', secondary: 'secondary', success: 'success', light: 'light', dark: 'dark', danger: 'danger',
        alarmatec: 'alarmatec', falla: 'falla', prealarma: 'prealarma', alarma: 'alarma',
        AT: 'alarmatec', FA: 'falla', PA: 'prealarma', AL: 'alarma', NO: 'info', DE: 'desconexion', EV: 'evento',

    };


    vm.reboot = function () {

        console.log("vm.reboot");
        const args = {
            message: 'Reiniciando equipo',
            level: 'info',
            level_class: 'info',
            level_img: 'info',
            timeStamp: new Date(),
        };

        pushEntryIntoScope(args);
        $scope.$applyAsync();

        if (typeof (nw) !== 'undefined') {
            //nw.App.quit();

//            nw.Shell.openItem('test.txt');
        }


    }


    vm.reconect_cons = function () {
        realTimeData.close();
        realTimeData.connect();
    }

    vm.hidebtn = () => {
        hidebtncnt++;
        $timeout.cancel(timeout);
        timeout = $timeout(function () {
            hidebtncnt = 0;
        }, 1000);

        if (hidebtncnt == 5) {
            vm.loglines = [];
            lineIdCounter = 0;
        }
    }

    realTimeData.rws.addEventListener('open', (event) => { 
/*
        const args = {
            message: 'Conexión de consola establecida',
            level: 'info',
            level_class: 'info',
            level_img: 'info',
            timeStamp: new Date(),
        };

        pushEntryIntoScope(args);
        $scope.$applyAsync();
*/

     });


    realTimeData.rws.addEventListener('error', (event) => { 
  /*
        const args = {
            message: 'No se pudo establecer la conexión de la consola',
            level: 'error',
            level_class: 'error',
            level_img: 'error',
            timeStamp: new Date(),
        };

        pushEntryIntoScope(args);
        $scope.$applyAsync();
*/

     });

    $scope.$on('io', function (event, args) {
        pushEntryIntoScope(args);
        $scope.$applyAsync();
    });

    /*
        $scope.$on('input', function (event, args) {
            pushEntryIntoScope(args);
            $scope.$applyAsync();
    
        });
    */
    $scope.$on('pantalla', function (event, args) {
        pushEntryIntoScope(args);
        //            $scope.$apply();
        $scope.$applyAsync();
    });

    $scope.$on('movcred', function (event, args) {
        pushEntryIntoScope(args);
        $scope.$applyAsync();
    });

    $scope.$on('sucesos', function (event, args) {
        pushEntryIntoScope(args);
        $scope.$applyAsync();
    });

    $scope.$on('estados', function (event, args) {
        if (args.context.EstadoDen == "RealTimeData")
            vm.conected = args.context.EstadoVal;
    });

    vm.$onInit = function () {
        vm.conected = (realTimeData.status() == 1) ? true : false;
//        vm.conected = true;
        if (typeof (nw) !== 'undefined') 
            vm.applocal = true;
//        vm.applocal = true;
      };

//    console.log("PRueba",electron);


    const highlightInLogEntry = function (entry) {
        for (const id in highlighted) {
            const item = highlighted[id];

            if (!item.text || !item.class) {
                continue;
            }

            while (entry.HtmlLine.indexOf(item.text) !== -1) {
                let text = item.text[0];
                text += '<span class=\'match-breaker\'></span>';
                text += item.text.substr(1, item.text.length - 1);
                entry.HtmlLine = entry.HtmlLine.replace(item.text, '<span class=\'highlight ' + item.class + '\'>' + text + '</span>');
            }
        }
    };

    const formatEntry = function (entry) {
        let message = entry.message;
        let title = '';
        const level = (entry.level) ? entry.level : 'primary';
        angular.forEach(entry.context, function (valor, campo: string) {
            if (campo.startsWith('stm')) {
                valor = $filter('ftDateTime')(valor);
            } else if (campo.startsWith('fec')) {
                valor = $filter('ftDate')(valor);
            } else if (campo.startsWith('cod_tema')) {
                title = valor;
            }
            message = message.replace('%' + campo, function () {
                if (campo === 'valor_analogico') {
                    return valor || '';
                }
                return valor;
            });
        });
        entry.HtmlLine = $filter('ftDateTime')(entry.timeStamp) + ' ' + message;
        entry.level_icon = level_icon[level];
        entry.level_class = level_class[level];
        entry.title = title;
        //            highlightInLogEntry(entry);
        //           entry.HtmlLine = $sce.trustAsHtml(entry.HtmlLine);
    };

    const pushEntryIntoScope = function (entry) {
        lineIdCounter++;
        entry.id = lineIdCounter;
        if (lineIdCounter === Number.MAX_VALUE)
            lineIdCounter = 0;

        if (!vm.loglines.filter(i => i.timeStamp === entry.timeStamp)[0]) {
            formatEntry(entry);
            vm.loglines.push(entry);
        }



        while (vm.loglines.length > maxLines) {
            vm.loglines.shift();
        }
    };
}


export default appConsoleComponent;
