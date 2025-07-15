'use strict';

import angular from "angular";

const logViewerComponent =
{
    template: require('../Pages/logviewer.html'),
    bindings: {
    },
    controllerAs: "logviewer",
    controller: ['$scope', 'websocketLogConstants', 'datosBack', '$interval', '$state', '$sce'
        , 'localData', function ($scope, websocketLogConstants, datosBack, $interval, $state, $sce, localData) {

            const vm = this;
            vm.logs = {};
            vm.numberOfLines = 45;
            vm.newSoureColor = '#FFFFFF';
            vm.loglines = [];
            vm.switch = false;
            vm.logs.discheck = true;
            vm.logList = {};

            localData.getlogList().then(function (resultado) {
                vm.logList = resultado;
            }).catch(function () { /* */ });

            let Timer;
            let lastTimespan = 0;
            let lineIdCounter = 0;
            const mystate = $state.$current;
            let posicion = 0;
            const highlighted = {};


            const highlightInLogEntry = function (entry) {
                for (const id in highlighted) {
                    const item = highlighted[id];

                    if (!item.text || !item.class) {
                        continue;
                    }

                    // var isSomethingHighlighted = false;
                    while (entry.HtmlLine.indexOf(item.text) !== -1) {
                        let text = item.text[0];
                        text += '<span class=\'match-breaker\'></span>';
                        text += item.text.substr(1, item.text.length - 1);
                        entry.HtmlLine = entry.HtmlLine.replace(item.text, '<span class=\'highlight ' + item.class + '\'>' + text + '</span>');
                        // isSomethingHighlighted = !item.silent;
                    }

                    // if (isSomethingHighlighted)
                    //  $scope.$emit(websocketLogConstants.events.highlighted, { text: item.text, 'class': item.class, highlighIid: item.id, lineId: entry.id, source: entry.source });
                }
            };
            

            const highlight = function (param) {
                if (param.text && param.text.length >= 2) {
                    highlighted[param.id] = param;
                    for (let i = 0; i < vm.loglines.length; i++) {
                        formatEntry(vm.loglines[i]);
                    }
                } else if (highlighted[param.id]) {
                    delete highlighted[param.id];
                }
            };

            const formatEntry = function (entry) {
                entry.HtmlLine = entry.Timestamp + ' ' + entry.Line;
                highlightInLogEntry(entry);
                entry.HtmlLine = $sce.trustAsHtml(entry.HtmlLine);
            };

            this.$onDestroy = function () {
                vm.switch = false;
            };
            
            mystate.onExit = function () {
                vm.$onDestroy();
            };
            
            const pushEntryIntoScope = function (entry) {
                entry.id = lineIdCounter++;
                if (lineIdCounter === Number.MAX_VALUE) {
                    lineIdCounter = 0;
                }
                formatEntry(entry);
                vm.loglines.push(entry);
                lastTimespan = entry.Timestamp;
                updateLogBoard();
            };
            
            const updateLogBoard = function () {
                vm.loglines.sort(logsorter);
                while (vm.loglines.length > vm.numberOfLines) {
                    vm.loglines.shift();
                }
            
                //        $scope.$$phase || $scope.$apply();
                //        window.scrollTo(0, document.body.scrollHeight);
            };
            
            const showMessage = function (line, color) {
                lastTimespan = lastTimespan + 1;
                pushEntryIntoScope({
                    Timestamp: lastTimespan,
                    Line: line,
                    color,
                });
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
            
            const stop = function () {
                $interval.cancel(Timer);
            };
            
            const start = function () {
                // stops any running interval to avoid two intervals running at the same time
                stop();
                // store the interval promise
                Timer = $interval(function () {
                    stop();
            
                    datosBack.logs(vm.logs.log_file, posicion).then(function (response) {
                        posicion = response.data.posicion;
                        const entries = response.data.data;
                        angular.forEach(entries, function (entry, key) {
                            pushEntryIntoScope(entry);
                            lastTimespan = entry.Timestamp;
                        });
                    }).catch(function (data) {
                    }).finally(function () {
                        if (vm.switch) {
                            start();
                        }
                    });
                }, 3000);
            };
            
            vm.filter = function (expression) {
                $scope.$broadcast(websocketLogConstants.commands.filter, { expression });
            };
            
            vm.highlight = function (highlightText) {
                highlight({ text: highlightText, id: 1, class: 'log-highlight' });
                // $scope.$broadcast(websocketLogConstants.commands.highlight, { text: highlightText, id: 1, 'class': 'log-highlight' });
            };
            
            /* no
            vm.$on(websocketLogConstants.events.highlighted, function (event, args) {
            });*/
            
            $scope.$watch('logController.switch', function (newValue, oldValue) {
                if (newValue === true) {
                    start();
                } else {
                    stop();
                }
            });
            
            vm.fselLog = function () {
                vm.loglines = [];
                vm.switch = false;
                posicion = 0;
                (vm.logs.log_file === '') ? vm.logs.discheck = true : vm.logs.discheck = false;
            };
            
            setTimeout(function () {
                highlight({ text: 'ERROR', id: 2, class: 'log-highlight-error', silent: true });
                highlight({ text: 'WARN', id: 3, class: 'log-highlight-warn', silent: true });
                // vm.switch=true;
            }, 500);
                        
                        
                
        }]
};

export default logViewerComponent;