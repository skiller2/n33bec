'use strict';
import angular, { element } from "angular";

const captureImageComponent = {
    template: require('../Pages/Templates/capture_image.html'),
//    selector : "capture-image",
        require: {
            ngModel: "ngModel"
        },
        bindings: {
            //            ngModel: '=',
            //ngModelCtrl: '=ngModel'
            showstream:'=showstream'
        },
        controllerAs: "ctrl",
        controller: ['$scope', 'datosBack', '$timeout', '$element','captureMedia', function ($scope, datosBack, $timeout, $element, captureMedia) {
            const vm = this;

            const nav: any = navigator;
            nav.getMedia = (nav.getUserMedia ||
                nav.webkitGetUserMedia ||
                nav.mozGetUserMedia ||
                nav.msGetUserMedia);

            // Checks if getUserMedia is available on the client browser
            (global as any).hasUserMedia = function () {
                return nav.getMedia ? true : false;
            };

            (global as any).hasModernUserMedia = 'mediaDevices' in navigator && 'getUserMedia' in nav.mediaDevices;

            const stillimg: any = $element.find('img');
            const videoElem = $element.find('video');
            let hiddenCanvas = document.createElement('canvas');
            let ctx: CanvasRenderingContext2D;
            let videoStream = null;
            let streamlive = false;

            let deviceNum = 0;


            vm.btn_takeSnapshot_disabled = true;
            vm.btn_adjuntar_disabled = true;
            vm.btn_camara_disabled = false;

            /*
            videoElem[0].addEventListener('canplay', function () {
                videoElem.show();
                stillimg.hide();
                vm.btn_takeSnapshot_disabled = false;
                vm.btn_adjuntar_disabled = false;
                vm.btn_camara_disabled = false;
                $scope.$applyAsync();

            }, false);
*/
            $scope.$watch(function () { return vm.ngModel.$modelValue }, function (newValue, oldValue) {
                //                if (newValue && newValue !== '') 
//                console.log('cambio');

                if (newValue == '' || newValue == undefined) {
                    stillimg[0].src = "data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=";
                } else {
                    stillimg[0].src = newValue;
                }
            });

            const stopStream = async function (deviceNum) {
                videoElem[0].pause();
                videoElem.hide();
                videoElem[0].srcObject = null;

                stillimg.show();
                vm.btn_takeSnapshot_disabled = true;
                vm.btn_adjuntar_disabled = false;
                $scope.$applyAsync();
                captureMedia.stopStream(deviceNum)
                streamlive = false;
            };
/*
            const onSuccess = function (stream) {
                console.log("onSuccess", stream);
                videoStream = stream;

                if ((global as any).hasModernUserMedia) {
                    videoElem[0].srcObject = stream;
                    // Firefox supports a src object
                } else if (nav.mozGetUserMedia) {
                    videoElem[0].mozSrcObject = stream;
                } else {
                    const vendorURL = window.URL || window.webkitURL;
                    videoElem[0].src = vendorURL.createObjectURL(stream);
                }

                // Start playing the video to show the stream from the webcam 
                videoElem[0].play();
            };
*/
            // called when any error happens
/*
            const onFailure = function (err) {
                console.log('fallo', err);
                vm.btn_takeSnapshot_disabled = true;
                vm.btn_adjuntar_disabled = false;
                $scope.$applyAsync();

                return;
            };

            vm.startWebcam = function (deviceId) {
                console.log("startWebcam con", deviceId);
                // Check the availability of getUserMedia across supported browsers
                if (!(window as any).hasUserMedia() && !(window as any).hasModernUserMedia) {
                    onFailure({ code: -1, msg: 'Browser does not support getUserMedia.' });
                    return;
                }

                const mediaConstraint = (deviceId) ? { video: { deviceId: { exact: deviceId } }, audio: false } : { video: true, audio: false };

                if ((window as any).hasModernUserMedia) {
                    nav.mediaDevices.getUserMedia(mediaConstraint)
                        .then(onSuccess)
                        .catch(onFailure);
                } else {
                    nav.getMedia(mediaConstraint, onSuccess, onFailure);
                }


            };
*/
            const getInfo = function () {
                return $element.is(':visible');
            };


            $scope.$watch(getInfo, function (val) {
                if (val) {
                    if (vm.showstream) { 
                        vm.showstream = false;
                        startStream(deviceNum);
                    }
//                    console.log('visible');
                    //                    vm.startWebcam();
                } else {
//                    console.log('no vis');
                    stopStream(deviceNum);
                }

            });

            vm.takeSnapshot = function () {
                ctx.drawImage(videoElem[0], 0, 0, hiddenCanvas.width, hiddenCanvas.height);
                vm.ngModel.$setViewValue(hiddenCanvas.toDataURL());
                ctx.clearRect(0, 0, hiddenCanvas.width, hiddenCanvas.height);
                vm.ngModel.$render();
                stopStream(deviceNum);
            };

            vm.borrarContenido = function () {
                vm.ngModel.$setViewValue('');
                vm.ngModel.$render();
            };

            vm.adjuntar = function () {
                angular.element('#adjunta_imagen').triggerHandler('click');
            };

            $element.bind('change', function (changeEvent) {
                $scope.fileinput = changeEvent.target.files[0];
                const reader = new FileReader();
                reader.onload = function (loadEvent) {
                    $scope.$apply(function () {
                        vm.ngModel.$setViewValue(loadEvent.target.result);
                        vm.ngModel.$render();
                    });
                };
                reader.readAsDataURL($scope.fileinput);
            });

            //$scope.$on('$destroy', vm.onDestroy); //No se si es necesario

            vm.LiveVideoOnOff = () => {
                if (streamlive)
                    stopStream(deviceNum);
                else 
                    startStream(deviceNum);

            }

            vm.$onDestroy = function () {
                stopStream(deviceNum);
            }; //end $onDestroy

            const startStream = async (deviceNum) => {
                captureMedia.getNewStream(deviceNum).then(function (stream) { 
    
                    vm.btn_camara_disabled = true;
                    handleStream(stream);
    
                }).catch();
            };

            const handleStream = (stream) => {
                videoElem[0].srcObject = stream;
                videoElem[0].play();
                videoElem.show();
                stillimg.hide();
                vm.btn_takeSnapshot_disabled = false;
                vm.btn_adjuntar_disabled = false;
                $scope.$applyAsync();
                /*
                play.classList.add('d-none');
                pause.classList.remove('d-none');
                screenshot.classList.remove('d-none');
                */
                streamlive = true;
                vm.btn_camara_disabled = false;

                stream.oninactive = function (event) {
                    stopStream(deviceNum);
                };



            };

            vm.$onInit = function () {
                deviceNum = $element.attr("device-num");
                hiddenCanvas.width= $element.attr('width') || '800';
                hiddenCanvas.height= $element.attr('height') || '600';
                ctx = hiddenCanvas.getContext('2d');

                if (angular.isDefined(vm.ngModel.$modelValue))
                    vm.ngModel.$modelValue = null; //Force scope model value and ngModel value to be out of sync to re-run formatters
            }; //end $onInit
        }],
    }
    ;

export default captureImageComponent;