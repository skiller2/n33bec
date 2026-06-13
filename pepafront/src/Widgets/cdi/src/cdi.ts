import * as angular from 'angular'

function RegisterDisplayCDI(dashboardProvider: { widget: (arg0: string, arg1: { title: string; description: string; controllerAs: string; controller: (string | ((datosBack: any, $scope: any, widget: any, globalData: any, auth: any, config: any, $interval: any, $timeout: any, CdiWidgetService: any, CDI_CONFIG: any) => void))[]; reload: boolean; template: any; titleTemplate: any; edit: { template: any; }; resolve: { config: (string | ((config: any) => any))[]; }; }) => void; }) {
    dashboardProvider
        .widget('cdi', {
            title: 'Display CDI',
            description: 'Display CDI',
            controllerAs: 'vm',
            // 'widget', 'globalData', 'auth', 'config',
            controller: ['$scope', '$interval', '$timeout', '$sce',  'CdiWidgetService', 'CDI_CONFIG',

                function ($scope: any, $interval: any, $timeout: any, $sce: any, CdiWidgetService: any, CDI_CONFIG: any) {

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
                    vm.offline = false;
                    vm.loading = true;
                    let consecutiveErrors = 0;
                    const OFFLINE_THRESHOLD = 2;

                    // Alert modal state
                    vm.alert = {
                        show: false,
                        title: '',
                        content: ''
                    };

                    // Widget state
                    vm.installationName = 'CDI';
                    vm.bars = [];
                    let currentLines: any[] = [];
                    let currentInputs: any[] = [];
                    let currentSystemBars: any[] = [];

                    vm.barStatus = {
                        // (left container)
                        alarm: false,
                        fault: false,
                        disconnect: false,
                        ground: false,
                        test: false,
                        extinction: false,
                        // (right container)
                        battery: 100,
                        powerSupply: true,
                        network: true
                    };

                    // Previous barStatus to detect changes
                    let previousBarStatus = {} as any;

                    // Previous lines and inputs to detect changes
                    let previousLinesData = {} as any;
                    let previousInputsData = {} as any;

                    vm.isConfigured = false;
                    vm.isModular = false;

                    // Buttons state
                    vm.buttons = {
                        acknowledge: true,
                        reset: false,
                        test: false
                    };

                    vm.buttonsDisabledByCmd = {
                        acknowledge: false,
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
                            loadInitialData();
                            loadStatusData();
                            startAutoRefresh();
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
                                } else {
                                    showAlert('Error', 'Error de autenticación. Credenciales inválidas.');
                                }
                            })
                            .catch(function (error: any) {
                                showAlert('Error', 'Error de autenticación');
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
                    }

                    function loadStatusData() {
                        var barPromise = CdiWidgetService.getBarStatus(vm.apiDomain)
                            .then(function (data: any) {
                                updateBarStatus(data.barstatus);
                                return true;
                            })
                            .catch(function (error: any) {
                                console.error('Error loading bar data:', error);
                                return false;
                            });

                        var linesPromise = CdiWidgetService.getLinesStatus(vm.apiDomain)
                            .then(function (data: any) {

                                // DATOS DEMOSTRACION
                                // const lines = [
                                //     { number: 1, status: 2, enable: 1, alias: 'Pasillo Norte' },
                                //     { number: 2, status: 6, enable: 1, alias: 'Cocina Central' },
                                //     { number: 3, status: 8, enable: 1, alias: 'Depósito A' }
                                // ];
                                // const inputs = [
                                //     { number: 1, status: 1, enable: 1, alias: 'Pulsador Emergencia' },
                                //     { number: 2, status: 5, enable: 1, alias: 'Sensor Humo' }
                                // ];
                                const lines = (data['LINEAS'] || [])
                                    .filter((line: any) => line.status !== 0);

                                const inputs = (data['ENTRADAS'] || [])
                                    .filter((input: any) => input.status !== 0);

                                if (!hasArrayChanged(previousLinesData, lines) && !hasArrayChanged(previousInputsData, inputs)) {
                                    return true;
                                }

                                currentLines = orderLines(lines);
                                currentInputs = orderInputs(inputs);
                                updateBars();

                                previousLinesData = angular.copy(lines);
                                previousInputsData = angular.copy(inputs);
                                return true;
                            })
                            .catch(function (error: any) {
                                console.error('Error loading lines data:', error);
                                return false;
                            });

                        // Track consecutive errors across both calls
                        (window as any).Promise.all([barPromise, linesPromise]).then(function (results: boolean[]) {
                            var anyError = results.some(function (r) { return r === false; });
                            if (anyError) {
                                consecutiveErrors++;
                                if (consecutiveErrors >= OFFLINE_THRESHOLD) {
                                    vm.loading = false;
                                    vm.offline = true;
                                }
                            } else {
                                var wasOffline = vm.offline;
                                consecutiveErrors = 0;
                                vm.offline = false;
                                vm.loading = false;

                                // Reintentar si se recupero la conexion o falta cargar datos
                                if (wasOffline || !vm.installationName) {
                                    loadInitialData();
                                }
                                if (wasOffline || !vm.isAuthenticated) {
                                    authenticate();
                                }
                            }
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
                        vm.isModular = barStatus.hasOwnProperty('MODULO_I1') || barStatus.hasOwnProperty('MODULO_I2') || barStatus.hasOwnProperty('MODULO_O1') || barStatus.hasOwnProperty('MODULO_O2');

                        vm.barStatus = {
                            alarm: barStatus['ALARMA'] || false,
                            fault: barStatus['FALLA'] || false,
                            disconnect: barStatus['DESCONEXION'] || false,
                            ground: barStatus['TIERRA'] || false,
                            test: barStatus['TEST'] || false,
                            extinction: barStatus['EXTINCION'] || false,
                            battery: barStatus['BATERIA'] || 0,
                            powerSupply: barStatus['ALIMENTACION'] || false,
                            network: barStatus['RED'] || false,
                            mod_i1: barStatus['MODULO_I1'] || false,
                            mod_i2: barStatus['MODULO_I2'] || false,
                            mod_o1: barStatus['MODULO_O1'] || false,
                            mod_o2: barStatus['MODULO_O2'] || false
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
                            previousBarStatus.network === vm.barStatus.network &&
                            previousBarStatus.mod_i1 === vm.barStatus.mod_i1 &&
                            previousBarStatus.mod_i2 === vm.barStatus.mod_i2 &&
                            previousBarStatus.mod_o1 === vm.barStatus.mod_o1 &&
                            previousBarStatus.mod_o2 === vm.barStatus.mod_o2) {
                            return;
                        }

                        currentSystemBars = [];

                        // if (vm.barStatus.alarm) currentSystemBars.push({ icon: 'bell', name: 'Alarma General', text: '', color: 'red' });
                        // if (vm.barStatus.fault) currentSystemBars.push({ icon: 'fault', name: 'Falla General', text: '', color: 'yellow' });
                        if (vm.barStatus.disconnect) currentSystemBars.push({ icon: 'disconnect', name: 'Falla', text: 'Desconexión', color: 'yellow' });
                        if (vm.barStatus.ground) currentSystemBars.push({ icon: 'groundconnection', name: 'Falla', text: 'Fuga a tierra', color: 'yellow' });
                        if (vm.barStatus.test) currentSystemBars.push({ icon: 'test', name: 'Equipo en prueba', text: '', color: 'green' });
                        if (vm.barStatus.extinction) currentSystemBars.push({ icon: 'extinction', name: 'Extinción', text: '', color: 'red' });

                        const battery = vm.barStatus.battery;
                        const batteryPercentage = battery.toString() + '%';


                        if (battery !== 100) {
                            currentSystemBars.push({ icon: 'batteryfault', name: 'Batería', text: batteryPercentage, color: 'yellow' });
                        }

                        if (!vm.barStatus.powerSupply) {
                            currentSystemBars.push({ icon: 'powersupplyfault', name: 'Alimentación', text: 'Falla', color: 'yellow' });
                        }

                        if (!vm.barStatus.network) {
                            currentSystemBars.push({ icon: 'networkfault', name: 'Red', text: 'Falla', color: 'yellow' });
                        }

                        if (vm.isModular) {
                            if (vm.barStatus.mod_i1 === false) currentSystemBars.push({ icon: 'disconnect', name: 'M_I1', text: 'Desconexión', color: 'yellow' });
                            if (vm.barStatus.mod_i2 === false) currentSystemBars.push({ icon: 'disconnect', name: 'M_I2', text: 'Desconexión', color: 'yellow' });
                            if (vm.barStatus.mod_o1 === false) currentSystemBars.push({ icon: 'disconnect', name: 'M_O1', text: 'Desconexión', color: 'yellow' });
                            if (vm.barStatus.mod_o2 === false) currentSystemBars.push({ icon: 'disconnect', name: 'M_O2', text: 'Desconexión', color: 'yellow' });
                        }

                        previousBarStatus = angular.copy(vm.barStatus);
                        updateBars();
                    }

                    function updateBars() {
                        const tempBars: any[] = [];

                        // Add system bars
                        angular.forEach(currentSystemBars, function (bar) {
                            tempBars.push({
                                icon: bar.icon,
                                color: bar.color,
                                title: bar.name,
                                text: bar.text
                            });
                        });

                        // Add lines
                        angular.forEach(currentLines, function (line) {
                            if (vm.shouldShowBar(line)) {
                                tempBars.push({
                                    icon: vm.getBarIcon('line', line.status),
                                    color: vm.getBarColor('line', line.status),
                                    title: vm.getBarName('line') + ' ' + line.number + ' ' + vm.getStatusText(line.status),
                                    text: line.alias
                                });
                            }
                        });

                        // Add inputs
                        angular.forEach(currentInputs, function (input) {
                            if (vm.shouldShowBar(input)) {
                                let inputTitle = vm.getBarName('input') + ' ' + input.number;
                                if (vm.isModular) {
                                    let modNum = Math.ceil(input.number / 7);
                                    let modName = 'M_I' + modNum;
                                    let modInNum = ((input.number - 1) % 7) + 1;
                                    inputTitle = modName + ' ' + vm.getBarName('input') + ' ' + modInNum;
                                }
                                tempBars.push({
                                    icon: vm.getBarIcon('input', input.status),
                                    color: vm.getBarColor('input', input.status),
                                    title: inputTitle + ' ' + vm.getStatusText(input.status),
                                    text: input.alias
                                });
                            }
                        });
                        // Priority order: Alarm (red), Pre-alarm (orange), Fault (yellow), Normal (green)
                        const priority = {
                            'red': 1,
                            'orange': 2,
                            'yellow': 3,
                            'green': 4
                        };

                        tempBars.sort(function (a, b) {
                            const pA = priority[a.color] || 99;
                            const pB = priority[b.color] || 99;
                            if (pA !== pB) return pA - pB;
                            return a.title.localeCompare(b.title);
                        });

                        vm.bars = tempBars;
                    }


                    vm.getSystemColor = function () {
                        if (!vm.bars || vm.bars.length === 0) return 'green';
                        const colors = vm.bars.map(function (b) { return b.color; });
                        if (colors.indexOf('red') !== -1) return 'red';
                        if (colors.indexOf('orange') !== -1) return 'orange';
                        if (colors.indexOf('yellow') !== -1) return 'yellow';
                        return 'green';
                    };

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
                                case 19: return 'red';
                                case 21: return 'red';
                                case 22: return 'red';
                                case 23: return 'red';
                                case 24: return 'red';
                                case 25: return 'red';
                                case 26: return 'red';
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
                                case 19: return 'bell';
                                case 21: return 'bell';
                                case 22: return 'bell';
                                case 23: return 'bell';
                                case 24: return 'bell';
                                case 25: return 'bell';
                                case 26: return 'bell';
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

                    function orderInputs(inputs: any) {
                        const customOrder = { 12: 0, 9: 1, 1: 2, 4: 3, 5: 4, 0: 5 };
                        return angular.copy(inputs).sort(function (a: any, b: any) {
                            return (customOrder[a.status] ?? 99) - (customOrder[b.status] ?? 99);
                        });
                    }

                    vm.shouldShowBar = function (bar: any) {
                        return bar.enable === 1;
                    };

                    // ==================== BUTTON LOGIC ====================

                    vm.buttonsDisabledByCmd = { acknowledge: false, reset: false, test: false };

                    vm.acknowledge = function () {
                        if (!vm.buttons.acknowledge || vm.buttonsDisabledByCmd.acknowledge) return;
                        vm.buttonsDisabledByCmd.acknowledge = true;
                        $timeout(function () {
                            vm.buttonsDisabledByCmd.acknowledge = false;
                        }, 2000);

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
                        if (!vm.buttons.reset || vm.buttonsDisabledByCmd.reset) return;
                        vm.buttonsDisabledByCmd.reset = true;
                        $timeout(function () {
                            vm.buttonsDisabledByCmd.reset = false;
                        }, 2000);

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
                        if (!vm.buttons.test || vm.buttonsDisabledByCmd.test) return;
                        vm.buttonsDisabledByCmd.test = true;
                        $timeout(function () {
                            vm.buttonsDisabledByCmd.test = false;
                        }, 2000);

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

                        if (vm.alertTimeout) {
                            $timeout.cancel(vm.alertTimeout);
                        }
                        vm.alertTimeout = $timeout(function () {
                            vm.alert.show = false;
                        }, 3500);
                    }

                    vm.closeAlert = function () {
                        vm.alert.show = false;
                        if (vm.alertTimeout) {
                            $timeout.cancel(vm.alertTimeout);
                        }
                    };



                    // ==================== INITIALIZATION ====================



                    vm.$onInit = function () {

                        updateStatusBarIcons();
                        initializeConfig();
                        console.log('CDI Widget initialized');
                    }
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
