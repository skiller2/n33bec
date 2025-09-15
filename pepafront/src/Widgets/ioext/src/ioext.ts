'use strict';

function RegisterIOExt(dashboardProvider) {
        dashboardProvider
          .widget('ioext', {
              title: 'IO Externo',
              description: 'IO Externo',
              controllerAs: 'widgetioext',
              controller: ['$interval','config','datosBack','datosBackIO','$scope','widget', function($interval,config,datosBack,datosBackIO,$scope,widget) {
                var vm = this;
                vm.io = {};
                widget.config.tiempo_recarga_seg = "10";
                widget.title = widget.config.io_name;
                var Timer;
                
                $scope.$on('$destroy', function(){
                     vm.stop();
                });
            
                function cargaWidget(){
                    datosBack.getData('ioext/'+widget.config.cod_equipo+'/'+widget.config.io_name,false,false).then(function(response){
                            vm.io = response;
                            widget.title = vm.io.io_label+" ("+vm.io.io_name+")";
                            if(vm.io.value=="0") vm.io.led = "led-"+widget.config.color_0;
                            else vm.io.led = "led-"+widget.config.color_1;
                            widget.config.showAlert=false;
                        }).catch(function (data) {
                            widget.config.showAlert=true;
                        });
                }
                
                vm.stop = function() {
                    $interval.cancel(Timer);
                };
            
                vm.start = function() {
                     // stops any running interval to avoid two intervals running at the same time
                    vm.stop();
                    Timer = $interval(function () {
                        cargaWidget();
                    }, config.tiempo_recarga_seg*1000);
                };
            
                vm.setData = function(io,valor)
                {
                };
                cargaWidget();
                vm.start();
                
            
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
          });
}

angular.module('adf.widget.ioext', ['adf.provider'])
    .config(['dashboardProvider', RegisterIOExt]);

