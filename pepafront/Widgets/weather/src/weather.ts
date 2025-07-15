/*
 * The MIT License
 *
 * Copyright (c) 2015, Sebastian Sdorra
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
 */

'use strict';

angular.module('adf.widget.weather', ['adf.provider'])
    .value('weatherApiKey', '2decdac859755da9d25281b20f0dc7a1')
    .value('weatherServiceUrl', '://api.openweathermap.org/data/2.5/weather?units=metric&q=')
    .config(['dashboardProvider', RegisterWeather])
    .service('weatherService', ['$q', '$http', '$sce', 'weatherServiceUrl', 'weatherApiKey', '$location', WeatherService])


function RegisterWeather(dashboardProvider) {
    dashboardProvider
        .widget('weather', {
            title: 'Weather',
            description: 'Display the current temperature of a city',
            template: require('./view.html'),
            controller: ['config', '$scope', 'widget', 'data', function (config, $scope, widget, data) {
                this.data = data;
            }],
            controllerAs: 'vm',
            reload: true,
            resolve: {
                data: ['weatherService', 'config', function (weatherService, config) {
                    if (config.location) {
                        return weatherService.get(config.location);
                    }
                    //return {};
                }]
            },
            edit: {
                template: require('./edit.html')
            }
        });
}



function WeatherService($q, $http, $sce, weatherServiceUrl, weatherApiKey, $location) {

    function get(location) {
        const deferred = $q.defer();
        var url = $location.protocol() + weatherServiceUrl + location + '&appid=' + weatherApiKey;
        url = $sce.trustAsResourceUrl(url);

        $http.jsonp(url, { jsonpCallbackParam: 'callback' })
            .then(function (response) {
                return response.data;
            })
            .then(function (data) {
                if (data && data.cod === 200) {
                    deferred.resolve(data);
                } else {
                    deferred.reject('weather service returned invalid body');
                }
            })
            .catch(function (e) {
                //                console.log('Error', e);
                deferred.reject('weather service returned ' + e.status);
            });

        return deferred.promise;
    }

    return {
        get: get
    };
}