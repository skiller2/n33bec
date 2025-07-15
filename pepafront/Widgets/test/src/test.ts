'use strict';
var Chart: any;
angular.module('adf.widget.test', ['adf.provider'])
    .config(['dashboardProvider', RegisterDiskSpace]);

function RegisterDiskSpace(dashboardProvider) {
        dashboardProvider
          .widget('test', {
              title: 'Espacio en disco',
              description: 'Espacio en disco',
              controllerAs: 'widgettest',
              controller: ['$interval', '$state', 'config', 'datosBack', '$scope',
              function($interval, $state, config, datosBack, $scope) {
              var vm = this;
              vm.labels = [];
              vm.data = [];
              vm.options = config.options;
      
              var Timer;
              var canceled=false;
      
              $scope.$on('$destroy', function(){
                   //$interval.cancel(promise);
                   canceled=true;
              });
      
              function cargaWidget(){
                  /*
                  var sel = JSON.stringify(config.selected_disk);
                  datosBack.getData('test/'+sel,false,false).then(function(response){
                        vm.labels = response.labels;
                        vm.data = response.data;
                      }).catch(function (data) {
                      }).finally(function() {
                          vm.start();
                      });
                      */
                     var pageload = {
                          name: 'page.load',
                          datapoints: [
                              { x: 2001, y: 1012 },
                              { x: 2002, y: 1023 },
                              { x: 2003, y: 1045 },
                              { x: 2004, y: 1062 },
                              { x: 2005, y: 1032 },
                              { x: 2006, y: 1040 },
                              { x: 2007, y: 1023 },
                              { x: 2008, y: 1090 },
                              { x: 2009, y: 1012 },
                              { x: 2010, y: 1012 },
                          ]
                      };
      
                      vm.config = {
                          //title: 'Line Chart',
                          //subtitle: 'Line Chart Subtitle',
                          debug: false,
                          showXAxis: true,
                          showYAxis: true,
                          showLegend: true,
                          stack: false,
                      };
      
                      vm.data = [ pageload ];
              }
      
              vm.stop = function() {
                  $interval.cancel(Timer);
              };
      
              vm.start = function() {
                   // stops any running interval to avoid two intervals running at the same time
                  vm.stop();
                  if (!canceled) {
                    Timer = $interval(function () {
                        vm.stop();
                        cargaWidget();
                    }, config.tiempo_recarga_seg*1000);
                  }
              };
      
              cargaWidget();
      }],
              template: require('./view.html'),
              reload: true,
              edit: {
                template: require('./edit.html')
              },
              resolve: {
                  config: ["config", function (config) {
                        return config;
                  }]
              }
          });
}

Chart.pluginService.register({
    beforeRender: function (chart) {
        if (chart.config.options.showAllTooltips) {
            // create an array of tooltips
            // we can't use the chart tooltip because there is only one tooltip per chart
            chart.pluginTooltips = [];
            chart.config.data.datasets.forEach(function (dataset, i) {
                chart.getDatasetMeta(i).data.forEach(function (sector, j) {
                    chart.pluginTooltips.push(new Chart.Tooltip({
                        _chart: chart.chart,
                        _chartInstance: chart,
                        _data: chart.data,
                        _options: chart.options.tooltips,
                        _active: [sector]
                    }, chart));
                });
            });

            // turn off normal tooltips
            chart.options.tooltips.enabled = false;
        }
    },
    afterDraw: function (chart, easing) {
        if (chart.config.options.showAllTooltips) {
            // we don't want the permanent tooltips to animate, so don't do anything till the animation runs atleast once
            if (!chart.allTooltipsOnce) {
                if (easing !== 1)
                    return;
                chart.allTooltipsOnce = true;
            }

            // turn on tooltips
            chart.options.tooltips.enabled = true;
            Chart.helpers.each(chart.pluginTooltips, function (tooltip) {
                tooltip.initialize();
                tooltip.update();
                // we don't actually need this since we are not animating tooltips
                tooltip.pivot();
                tooltip.transition(easing).draw();
            });
            chart.options.tooltips.enabled = false;
        }
        if (chart.config.options.showPieValues) {
            var ctx = chart.chart.ctx;
            ctx.font = Chart.helpers.fontString(Chart.defaults.global.defaultFontFamily, 'normal', Chart.defaults.global.defaultFontFamily);
            ctx.textAlign = 'center';
            ctx.textBaseline = 'bottom';

            chart.config.data.datasets.forEach(function (dataset) {
              for (var i = 0; i < dataset.data.length; i++) {
                var model = dataset._meta[Object.keys(dataset._meta)[0]].data[i]._model,
                    total = dataset._meta[Object.keys(dataset._meta)[0]].total,
                    mid_radius = model.innerRadius + (model.outerRadius - model.innerRadius)/2,
                    start_angle = model.startAngle,
                    end_angle = model.endAngle,
                    mid_angle = start_angle + (end_angle - start_angle)/2;

                var x = mid_radius * Math.cos(mid_angle);
                var y = mid_radius * Math.sin(mid_angle);

                ctx.fillStyle = '#fff';
                if (i == 3){ // Darker text color for lighter background
                  ctx.fillStyle = '#444';
                }
                var percent = String(Math.round(dataset.data[i]/total*100)) + "%";
                ctx.fillText(dataset.data[i], model.x + x, model.y + y);
                // Display percent in another line, line break doesn't work for fillText
                ctx.fillText(percent, model.x + x, model.y + y + 15);
              }
          });
      }
    }
});
