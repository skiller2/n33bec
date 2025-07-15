'use strict';

import angular from "angular";

const rangeSelectComponent = {
    template: require('../Pages/Templates/range_select_template.html'),
    controllerAs: "vm",
    bindings: {
        JSONdata: '=ngModel',
    },
    require: { modelCtrl: 'ngModel' },
    controller: ['$element', function ($element) {
        const vm = this;

        vm.addRange = function () {
            if (vm.desde !== '' && vm.hasta !== '') {
                const range = { d: vm.desde, h: vm.hasta };
                if (!angular.isArray(vm.JSONdata)) {
                    vm.JSONdata = [];
                }
                vm.JSONdata.push(range);
                vm.desde = '';
                vm.hasta = '';
                vm.modelCtrl.$setDirty();
            }
        };
        vm.delRange = function (pos) {
            if (!angular.isArray(vm.JSONdata)) {
                vm.JSONdata = [];
            }
            vm.JSONdata.splice(pos, 1);
            vm.modelCtrl.$setDirty();

        };

        //vm.fileinput
        vm.$onInit =  () => {
    
        }

        vm.$onChanges = (changes) => {
        }


    }]
};

export default rangeSelectComponent;
