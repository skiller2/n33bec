'use strict';

import angular from "angular";
        
const display2Component = {
    template: require('../Pages/display2.html'),
    bindings: {},
    controllerAs: "display2",
    controller: ['$scope', 'realTimeData', '$sce', '$filter', 'datosBack','$translate',
        function ($scope, realTimeData, $sce, $filter, datosBack,$translate) {

            const vm = this;
            vm.loglines = [];
            vm.message1 = ''; // "PRUEBA                             11111";
            vm.message2 = ''; // "PRUEBA                             22222";
            vm.message3 = ''; // "PRUEBA                             33333";
            vm.message4 = ''; // "PRUEBA                             44444";
            vm.suceso = 0;

            vm.io_name03 = 'IO03';
            vm.io_name04 = 'IO04';
            vm.io_name17 = 'IO17';
            vm.value = 0;
            vm.des_valor = $translate.instant('Normal');
            vm.button = 'btn-success';
            vm.d03src = '';
            vm.d04src = '';
            vm.d07src = '';

            $scope.$on('io', function (event, args) {
                if (args.context.io_name === vm.io_name03) {
                    vm.d03src = '../Content/Images/d03' + args.context.color + '.png';
                }
                if (args.context.io_name === vm.io_name04) {
                    vm.d04src = '../Content/Images/d04' + args.context.color + '.png';
                }
                if (args.context.io_name === vm.io_name17) {
                    vm.d07src = '../Content/Images/d07' + args.context.color + '.png';
                }

            });

            vm.setData = function (io_name, value) {
                datosBack.save('', 'directio', { io_name, value }, '').then(function (response) {

                }).catch(function (data) {
                }).finally(function () {
                    // cargaWidget();
                });
            };

            vm.$onDestroy = function () {
            };

            vm.$onInit = function () {

                realTimeData.connect();

                datosBack.getData('io/1234/' + vm.io_name03, false, false).then(function (response) {
                    if (response.io_name === vm.io_name03) {
                        vm.d03src = '../Content/Images/d03' + response.color + '.png';
                    }
                }).catch(function (data) { });

                datosBack.getData('io/1234/' + vm.io_name04, false, false).then(function (response) {
                    if (response.io_name === vm.io_name04) {
                        vm.d04src = '../Content/Images/d04' + response.color + '.png';
                    }
                }).catch(function (data) { });

                datosBack.getData('io/1234/' + vm.io_name17, false, false).then(function (response) {
                    if (response.io_name === vm.io_name17) {
                        vm.d07src = '../Content/Images/d07' + response.color + '.png';
                    }
                }).catch(function (data) { });

            };

            /*****************************************/
            // WEB SOCKET
            const highlighted = {};
            let lineIdCounter = 0;
            vm.numberOfLines = 45;
            $scope.$on('io', function (event, args) {
                pushEntryIntoScope(args);
            });

            $scope.$on('smallscreen', function (event, args) {

                if (args.context[0].lin === 0) {
                    vm.message1 = args.context[0].info;
                }
                if (args.context[0].lin === 1) {
                    vm.message2 = args.context[0].info;
                }
                if (args.context[0].lin === 2) {
                    vm.message3 = args.context[0].info;
                }
                if (args.context[0].lin === 3) {
                    vm.message4 = args.context[0].info;
                }

            });

            $scope.$on('pantalla', function (event, args) {
                pushEntryIntoScope(args);
            });

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
                angular.forEach(entry.context, function (valor, campo: string) {
                    if (campo.startsWith('stm')) {
                        valor = $filter('ftDateTime')(valor);
                    } else if (campo.startsWith('fec')) {
                        valor = $filter('ftDate')(valor);
                    }
                    message = message.replace('%' + campo, function () {
                        if (campo === 'valor_analogico') {
                            return valor || '';
                        }
                        return valor;
                    });
                });
                entry.HtmlLine = $filter('ftDateTime')(entry.timeStamp) + ' ' + message;
                highlightInLogEntry(entry);
                entry.HtmlLine = $sce.trustAsHtml(entry.HtmlLine);
            };

            const pushEntryIntoScope = function (entry) {
                entry.id = lineIdCounter++;
                if (lineIdCounter === Number.MAX_VALUE) {
                    lineIdCounter = 0;
                }
                formatEntry(entry);
                vm.loglines.unshift(entry);
                updateLogBoard();
            };

            const logsorter = function (a, b) {
                if (a.Timestamp < b.Timestamp) {
                    return -1;
                } else if (a.Timestamp > b.Timestamp) {
                    return 1;
                } else {
                    return 0;
                }
            };

            const updateLogBoard = function () {
                // vm.loglines.sort(logsorter);
                while (vm.loglines.length > vm.numberOfLines) {
                    vm.loglines.pop();
                }
            };

        }]
};

export default display2Component;