import * as angular from 'angular'
import './cdi.css';

function RegisterDisplayCDI(dashboardProvider: { widget: (arg0: string, arg1: { title: string; description: string; controllerAs: string; controller: (string | ((datosBack: any, $scope: any, widget: any, globalData: any, auth: any, config: any, $interval: any, $timeout: any, CdiWidgetService: any, CDI_CONFIG: any) => void))[]; reload: boolean; template: any; titleTemplate: any; edit: { template: any; }; resolve: { config: (string | ((config: any) => any))[]; }; }) => void; }) {
    dashboardProvider
        .widget('cdi', {
            title: 'Display CDI',
            description: 'Display CDI',
            controllerAs: 'vm',
            // 'widget', 'globalData', 'auth', 'config',
            controller: [ 'datosBack', '$scope', '$interval', '$sce', 'CdiWidgetService', 'CDI_CONFIG',
                function (datosBack: any, $scope: any, $interval: any, $sce: any, CdiWidgetService: any, CDI_CONFIG: any) {

                    // ==================== INITIALIZATION ====================

                    $scope.$on('$destroy', function () {
                        console.log('CDI Widget destroyed');
                        if (vm.refreshInterval) {
                            $interval.cancel(vm.refreshInterval);
                        }
                    });



                    const vm = this as any;


                    vm.getIcon = function (name: string) {
                        var html = '<svg class="icon-svg"><use xlink:href="#icon-' + name + '"></use></svg>';
                        return $sce.trustAsHtml(html);
                    };

                    vm.isAuthenticated = false;
                    vm.showLoader = false;
                    vm.loaderText = '';

                    // Alert modal state
                    vm.alert = {
                        show: false,
                        title: '',
                        content: ''
                    };

                    // Widget state
                    vm.installationName = '';
                    vm.lines = [];
                    vm.inputs = [];
                    // DATOS DEMOSTRACION
                    // vm.lines = [
                    //     { number: 1, status: 2, enable: 1, alias: 'Pasillo Norte' },
                    //     { number: 2, status: 6, enable: 1, alias: 'Cocina Central' },
                    //     { number: 3, status: 8, enable: 1, alias: 'Depósito A' }
                    // ];
                    // vm.inputs = [
                    //     { number: 1, status: 1, enable: 1, alias: 'Pulsador Emergencia' },
                    //     { number: 2, status: 5, enable: 1, alias: 'Sensor Humo' }
                    // ];
                    vm.barStatus = {
                        // (left container)
                        alarm: false,
                        fault: false,
                        disconnect: false,
                        ground: false,
                        test: false,
                        extinction: false,
                        // (right container)
                        battery: 0,
                        powerSupply: false,
                        network: false
                    };

                    // Cached icon arrays (to prevent infinite digest)
                    vm.statusBarIconsLeft = [];
                    vm.statusBarIconsRight = [];

                    // Previous barStatus to detect changes
                    let previousBarStatus = {} as any;

                    // Previous lines and inputs to detect changes
                    let previousLinesData = {} as any;
                    let previousInputsData = {} as any;

                    vm.isConfigured = false;

                    // Buttons state
                    vm.buttons = {
                        acknowledge: true,
                        reset: false,
                        test: false
                    };

                    // Language
                    vm.language = vm.language || CDI_CONFIG.DEFAULT_LANGUAGE;

                    // ==================== CONFIGURATION ====================

                    /**
                     * Initialize configuration from attributes
                     */
                    function initializeConfig() {
                        vm.apiDomain = vm.apiDomain || 'cdi';
                        vm.userId = parseInt(vm.userId || 2);
                        vm.userCode = vm.userCode || '2222';
                        vm.language = vm.language || CDI_CONFIG.DEFAULT_LANGUAGE;

                        if (vm.apiDomain && vm.userId && vm.userCode) {
                            vm.isConfigured = true;
                            authenticate();
                        }
                    }

                    /**
                     * Authenticate user with the API
                     */
                    function authenticate() {
                        CdiWidgetService.authenticateUser(vm.apiDomain, vm.userId, vm.userCode)
                            .then(function (result: any) {
                                if (result.success) {
                                    vm.isAuthenticated = true;
                                    vm.buttons.reset = true;
                                    vm.buttons.test = true;
                                    console.log('User authenticated successfully');
                                    loadInitialData();
                                    startAutoRefresh();
                                } else {
                                    showAlert('Error', 'Authentication failed. Invalid credentials.');
                                }
                            })
                            .catch(function (error: any) {
                                showAlert('Error', 'Authentication error: ' + error.message);
                            });
                    }

                    // ==================== DATA FETCHING ====================

                    function loadInitialData() {
                        CdiWidgetService.getGeneralConfig(vm.apiDomain)
                            .then(function (config: any) {
                                if (config && config.cfgGeneral && config.cfgGeneral['NAME']) {
                                    vm.installationName = config.cfgGeneral['NAME'];
                                }
                            })
                            .catch(function (error: any) {
                                console.error('Error loading general config:', error);
                            });

                        loadStatusData();
                    }

                    function loadStatusData() {
                        CdiWidgetService.getBarStatus(vm.apiDomain)
                            .then(function (data: any) {
                                updateBarStatus(data.barstatus);
                            })
                            .catch(function (error: any) {
                                console.error('Error loading bar data:', error);
                            });

                        CdiWidgetService.getLinesStatus(vm.apiDomain)
                            .then(function (data: any) {
                                const lines = data['LINEAS'] || []
                                    .filter((line: any) => line.status !== 0);
                                const inputs = data['ENTRADAS'] || []
                                    .filter((input: any) => input.status !== 0);

                                if (!hasArrayChanged(previousLinesData, lines) && !hasArrayChanged(previousInputsData, inputs)) {
                                    return;
                                }

                                vm.lines = orderLines(lines);
                                vm.inputs = orderInputs(inputs);

                                previousLinesData = angular.copy(lines);
                                previousInputsData = angular.copy(inputs);
                            })
                            .catch(function (error: any) {
                                console.error('Error loading lines data:', error);
                            });
                    }

                    function startAutoRefresh() {
                        if (vm.refreshInterval) {
                            $interval.cancel(vm.refreshInterval);
                        }

                        vm.refreshInterval = $interval(function () {
                            loadStatusData();
                        }, CDI_CONFIG.POLLING_INTERVAL || 2500);


                    }

                    function hasArrayChanged(previousArray: any[], currentArray: any[]) {
                        if (!previousArray) return true;
                        if (previousArray.length !== currentArray.length) return true;
                        for (var i = 0; i < currentArray.length; i++) {
                            var prev = previousArray[i];
                            var curr = currentArray[i];
                            if (prev.number !== curr.number ||
                                prev.status !== curr.status ||
                                prev.enable !== curr.enable ||
                                prev.alias !== curr.alias) {
                                return true;
                            }
                        }
                        return false;
                    }

                    // ==================== RENDERING LOGIC ====================

                    function updateBarStatus(barStatus: any) {
                        vm.barStatus = {
                            alarm: barStatus['ALARMA'] || false,
                            fault: barStatus['FALLA'] || false,
                            disconnect: barStatus['DESCONEXION'] || false,
                            ground: barStatus['TIERRA'] || false,
                            test: barStatus['TEST'] || false,
                            extinction: barStatus['EXTINCION'] || false,
                            battery: barStatus['BATERIA'] || 0,
                            powerSupply: barStatus['ALIMENTACION'] || false,
                            network: barStatus['RED'] || false
                        };
                        updateStatusBarIcons();
                    }

                    function updateStatusBarIcons() {
                        if (previousBarStatus &&
                            previousBarStatus.alarm === vm.barStatus.alarm &&
                            previousBarStatus.fault === vm.barStatus.fault &&
                            previousBarStatus.disconnect === vm.barStatus.disconnect &&
                            previousBarStatus.ground === vm.barStatus.ground &&
                            previousBarStatus.test === vm.barStatus.test &&
                            previousBarStatus.extinction === vm.barStatus.extinction &&
                            previousBarStatus.battery === vm.barStatus.battery &&
                            previousBarStatus.powerSupply === vm.barStatus.powerSupply &&
                            previousBarStatus.network === vm.barStatus.network) {
                            return;
                        }

                        vm.statusBarIconsLeft = [];
                        if (vm.barStatus.alarm) vm.statusBarIconsLeft.push({ name: 'bell', alt: 'Alarma' });
                        if (vm.barStatus.fault) vm.statusBarIconsLeft.push({ name: 'fault', alt: 'Falla' });
                        if (vm.barStatus.disconnect) vm.statusBarIconsLeft.push({ name: 'disconnect', alt: 'Desconexión' });
                        if (vm.barStatus.ground) vm.statusBarIconsLeft.push({ name: 'groundconnection', alt: 'Tierra' });
                        if (vm.barStatus.test) vm.statusBarIconsLeft.push({ name: 'test', alt: 'Test' });
                        if (vm.barStatus.extinction) vm.statusBarIconsLeft.push({ name: 'extinction', alt: 'Extinción' });

                        const battery = vm.barStatus.battery;
                        let batteryIcon = 'batteryfault';
                        let batteryAlt = 'Batería: Falla';

                        if (battery === 100) { batteryIcon = 'battery100'; batteryAlt = 'Batería: 100%'; }
                        else if (battery >= 75) { batteryIcon = 'battery75'; batteryAlt = 'Batería: 75%'; }
                        else if (battery >= 50) { batteryIcon = 'battery50'; batteryAlt = 'Batería: 50%'; }
                        else if (battery <= 25 && battery > 1) { batteryIcon = 'battery25'; batteryAlt = 'Batería: 25%'; }

                        const powerIcon = vm.barStatus.powerSupply ? 'powersupplynormal' : 'powersupplyfault';
                        const powerAlt = vm.barStatus.powerSupply ? 'Alimentación OK' : 'Falla de alimentación';
                        const networkIcon = vm.barStatus.network ? 'networknormal' : 'networkfault';
                        const networkAlt = vm.barStatus.network ? 'Red conectada' : 'Red desconectada';

                        vm.statusBarIconsRight = [
                            { name: batteryIcon, alt: batteryAlt },
                            { name: powerIcon, alt: powerAlt },
                            { name: networkIcon, alt: networkAlt }
                        ];

                        previousBarStatus = angular.copy(vm.barStatus);
                    }

                    vm.getBarColor = function (type: string, status: number) {
                        if (type === 'line') {
                            switch (status) {
                                case 0: return 'green';
                                case 2: return 'red';
                                case 3: return 'orange';
                                case 4: return 'red';
                                case 6: return 'yellow';
                                case 7: return 'yellow';
                                case 8: return 'yellow';
                                default: return 'green';
                            }
                        } else {
                            switch (status) {
                                case 0: return 'green';
                                case 1: return 'red';
                                case 4: return 'red';
                                case 5: return 'yellow';
                                case 8: return 'yellow';
                                case 9: return 'red';
                                case 12: return 'red';
                                default: return 'green';
                            }
                        }
                    };

                    vm.getBarIcon = function (type: string, status: number) {
                        if (type === 'line') {
                            switch (status) {
                                case 0: return 'check';
                                case 2: return 'bell';
                                case 3: return 'bell';
                                case 4: return 'bell';
                                case 6: return 'fault';
                                case 7: return 'fault';
                                case 8: return 'disconnect';
                                default: return 'check';
                            }
                        } else {
                            switch (status) {
                                case 0: return 'check';
                                case 1: return 'bell';
                                case 4: return 'bell';
                                case 5: return 'fault';
                                case 8: return 'fault';
                                case 9: return 'bell';
                                case 12: return 'bell';
                                default: return 'check';
                            }
                        }
                    };

                    vm.getBarName = function (type: string) {
                        if (type === 'input') return CDI_CONFIG.DICTIONARY.main.bar.input.name[vm.language] || 'Input';
                        return CDI_CONFIG.DICTIONARY.main.bar.line.name[vm.language] || 'Line';
                    };

                    vm.getStatusText = function (status: number) {
                        return CDI_CONFIG.STATUS_LYE[vm.language][status] || 'Unknown';
                    };

                    vm.t = function (path: string) {
                        const keys = path.split('.');
                        let value = CDI_CONFIG.DICTIONARY;
                        for (let i = 0; i < keys.length; i++) {
                            if (value && value[keys[i]]) value = value[keys[i]];
                            else return path;
                        }
                        if (value && typeof value === 'object' && value[vm.language]) return value[vm.language];
                        return value;
                    };

                    function orderLines(lines: any[]) {
                        const customOrder = { 2: 0, 3: 1, 4: 2, 6: 3, 7: 4, 8: 5, 0: 6 };
                        return angular.copy(lines).sort(function (a: any, b: any) {
                            return (customOrder[a.status] ?? 99) - (customOrder[b.status] ?? 99);
                        });
                    }

                    function orderInputs(inputs) {
                        const customOrder = { 12: 0, 9: 1, 1: 2, 4: 3, 5: 4, 0: 5 };
                        return angular.copy(inputs).sort(function (a, b) {
                            return (customOrder[a.status] ?? 99) - (customOrder[b.status] ?? 99);
                        });
                    }

                    vm.shouldShowBar = function (bar: any) {
                        return bar.enable === 1;
                    };

                    // ==================== BUTTON LOGIC ====================

                    vm.acknowledge = function () {
                        if (!vm.buttons.acknowledge) return;
                        vm.showLoader = true;
                        vm.loaderText = CDI_CONFIG.DICTIONARY.modals.loader.header[vm.language];
                        CdiWidgetService.sendAcknowledge(vm.apiDomain, vm.userId)
                            .then(function (response: any) {
                                vm.showLoader = false;
                                showAlert(CDI_CONFIG.DICTIONARY.modals.alert.acknowledge.success.header[vm.language], CDI_CONFIG.DICTIONARY.modals.alert.acknowledge.success.content[vm.language]);
                            })
                            .catch(function (error: any) {
                                vm.showLoader = false;
                                showAlert(CDI_CONFIG.DICTIONARY.modals.alert.acknowledge.error.header[vm.language], CDI_CONFIG.DICTIONARY.modals.alert.acknowledge.error.content[vm.language]);
                            });
                    };

                    vm.reset = function () {
                        if (!vm.buttons.reset) return;
                        vm.showLoader = true;
                        vm.loaderText = CDI_CONFIG.DICTIONARY.modals.loader.header[vm.language];
                        CdiWidgetService.sendReset(vm.apiDomain, vm.userId)
                            .then(function (response: any) {
                                vm.showLoader = false;
                                showAlert(CDI_CONFIG.DICTIONARY.modals.alert.reset.success.header[vm.language], CDI_CONFIG.DICTIONARY.modals.alert.reset.success.content[vm.language]);
                            })
                            .catch(function (error: any) {
                                vm.showLoader = false;
                                showAlert(CDI_CONFIG.DICTIONARY.modals.alert.reset.error.header[vm.language], CDI_CONFIG.DICTIONARY.modals.alert.reset.error.content[vm.language]);
                            });
                    };

                    vm.test = function () {
                        if (!vm.buttons.test) return;
                        vm.showLoader = true;
                        vm.loaderText = CDI_CONFIG.DICTIONARY.modals.loader.header[vm.language];
                        CdiWidgetService.sendTest(vm.apiDomain, vm.userId)
                            .then(function (response: any) {
                                vm.showLoader = false;
                                showAlert(CDI_CONFIG.DICTIONARY.modals.alert.test.success.header[vm.language], CDI_CONFIG.DICTIONARY.modals.alert.test.success.content[vm.language]);
                            })
                            .catch(function (error: any) {
                                vm.showLoader = false;
                                showAlert(CDI_CONFIG.DICTIONARY.modals.alert.test.error.header[vm.language], CDI_CONFIG.DICTIONARY.modals.alert.test.error.content[vm.language]);
                            });
                    };

                    function showAlert(title: string, content: string) {
                        vm.alert.show = true;
                        vm.alert.title = title;
                        vm.alert.content = content;
                    }

                    vm.closeAlert = function () {
                        vm.alert.show = false;
                    };



                    // ==================== INITIALIZATION ====================




                    vm.$onInit = function () {

                        updateStatusBarIcons();
                        initializeConfig();
                        console.log('CDI Widget initialized');
                    };


                    vm.$onInit()








                    /*
                    
                                    const vm = this;
                                    vm.linea1 = "".padEnd(40,' ');
                                    vm.linea2 = "".padEnd(40,' ');
                                    vm.linea3 = "".padEnd(40,' ');
                                    vm.linea4 = "".padEnd(40,' ');
                                    vm.status = 0;
                                    vm.display_buzzer=0
                                    vm.display_falla=0
                            
                                    const defDisplay = () => { 
                                        vm.linea1 = "                                        ";
                                        vm.linea2 = "                SIN                     ";
                                        vm.linea3 = "             CONEXIÓN                   ";
                                        vm.linea4 = "                                        ";
                                    }
                            
                            
                                    function cargaWidget() {
                                        datosBack.getData('display_cdi/'+btoa(config.cod_tema), false, false).then(function (response) {
                                        }).catch(function (data) {
                                        });
                                    }
                            
                                    vm.sendCMD = (cmd: string) => {
                                        console.log('recibido', cmd,config.cod_tema)
                                        const cod_tema = config.cod_tema+"/00/000"; 
                                        //reset //up //down //left //right //ack
                                        return datosBack.save('proceso', 'displaysucesos/cmdcentral', { cod_tema: cod_tema,cmd:cmd }, '')
                                            .then(function () {
                                            })
                                            .catch(function () { });
                                    }
                            
                    
                                    $scope.$on('auth', function (event, args) {
                                        if (args.authenticated) 
                                            cargaWidget();
                                    });
                            
                                    $scope.$on('display_cdi', function (event, args) {
                                        if (args.context.display && config.cod_tema == args.context.cod_tema) {
                                            vm.status = (args.context.display_status) ? args.context.display_status:0;
                                            vm.command_enabled = (args.context.command_enabled) ? args.context.command_enabled:0;
                                            if (!vm.linea1 && !vm.linea2 && !vm.linea3 && !vm.linea4) {
                                                defDisplay();
                                            } else {
                                                vm.linea1 = args.context.display[1].padEnd(40,' ');
                                                vm.linea2 = args.context.display[2].padEnd(40,' ');
                                                vm.linea3 = args.context.display[3].padEnd(40,' ');
                                                vm.linea4 = args.context.display[4].padEnd(40,' ');
                                            }
                                        }
                    
                                        vm.display_alarma = vm.status >> 1 & 1;
                                        vm.display_falla = vm.status >> 2 & 1;
                                        vm.display_buzzer = vm.status >> 6 & 1;
                    
                    //                    console.log('display_alarma ', vm.display_alarma)
                    //                    console.log('display_falla ',vm.display_falla)
                    //                    console.log('display_buzzer ',vm.display_buzzer)
                    
                                    });
                            
                            //        vm.$onInit = function () {
                            //            auth.isLogged();
                            //       }
                                    if (auth.isLoggedIn())
                                        cargaWidget();
                         */
                }],
            reload: true,
            template: require('./view.html'),
            titleTemplate: require('../../widget-title.html'),
            edit: {
                template: require('./edit.html')
            },
            resolve: {
                config: ['config', function (config) {
                    return config;
                }]
            }
        }
        );

}

angular.module('adf.widget.cdi', ['adf.provider'])
    .config(['dashboardProvider', RegisterDisplayCDI]);
