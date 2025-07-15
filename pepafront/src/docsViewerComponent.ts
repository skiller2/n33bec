'use strict';
import angular, { element } from "angular";
import { chmod } from "fs";
const docsViewerComponent= {
        template: require('../Pages/Templates/docs_viewer.html'),
        require: {
            //            ngModel: "ngModel"
        },
        bindings: {
            "folderId": '=folderId',
            "onClose": "&onClose",
            "url": "=url"
        },
        controllerAs: "ctrl",
        controller: ['$scope', 'datosBack', '$timeout', '$element', '$sce', '$http', function ($scope, datosBack, $timeout, $element, $sce, $http) {
            const vm = this;
            const iframe = $element.find("iframe");
            vm.folderId = ""
            /*
                        $scope.$watch(function () { return vm.ngModel.$modelValue }, function (newValue, oldValue) {
                            //                if (newValue && newValue !== '') 
                            console.log('cambio');
            
                            if (newValue == '' || newValue == undefined) {
                                stillimg[0].src = "data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=";
                            } else {
                                stillimg[0].src = newValue;
                            }
                        });
            */

            $element.bind('change', function (changeEvent) {
            });

            //$scope.$on('$destroy', vm.onDestroy); //No se si es necesario
            vm.viewDocList = () => {
                let url = "";
                if (vm.folderId) {
                    url = "https://drive.google.com/embeddedfolderview?id=" + vm.folderId + "#grid";
                } else if (vm.url) { 
                    url = vm.url;
                }
                console.log("log", url,vm.url);
                iframe.attr("src", $sce.trustAsResourceUrl(url));
            }


            vm.onCloseBtn = () => {
                vm.onClose();
            }

            vm.$onDestroy = function () {
            }; //end $onDestroy


            vm.$onInit = function () {

/*
                let w = remote.getCurrentWindow();




                vm.viewDocList();


                w.close();
                app.exit(0);



                webContents.on('new-window', (event, url) => {
                    event.defaultPrevented = true;
                    event.preventDefault();
                    win.loadURL(url);
                })
*/

$timeout(function() {
    vm.viewDocList();
  },1000);
                
                
                if (typeof (nw) !== 'undefined') {
                    var win = nw.Window.get();
                    win.on('new-win-policy', newWinPolicyHandler);
    
    

                    function newWinPolicyHandler(frame, url, policy) {
                        policy.ignore(); //ignore policy first to prevent popup
                        const res = url.match(/file\/d\/(.*)\//);
                        if (res[1])
                            iframe.attr("src", $sce.trustAsResourceUrl("https://drive.google.com/file/d/"+res[1]+"/preview?usp=embed_googleplus"));
                    }

                }
    



  
//                vm.srcdoc = "<p>HOLA MUNDO</p>";

                iframe.bind('load', function (evt) {
                    let alel = $element.contents();

                    console.log('todos los a', iframe, alel);
                });

                //$element.src =
                //                vm.docslisturl = $sce.trustAsResourceUrl("https://drive.google.com/embeddedfolderview?id=1skmoBSRT6m_UKbdR--qKxlA0x30blurF#grid");


                //vm.docslisturl = $sce.trustAsResourceUrl("https://drive.google.com/file/d/1osd3kavbkYX3owhUbnB3tf4lsWaTnsKl/preview?usp=embed_googleplus");
            }; //end $onInit
        }],
    };

export default docsViewerComponent;