'use strict';
import angular from "angular";

const diaHoraEmpleadoComponent = {
    template: require('../Pages/Templates/dia_hora_template.html'),
    controllerAs: "vm",
    bindings: {
        ngModel: '<',
        callback: '&',
    },
    require: { modelCtrl: 'ngModel' },
    controller: ['$element', function ($element) {
        const vm = this;

        var JSONdata = [
            { d: '0', hi: '', he: '' },
            { d: '1', hi: '', he: '' },
            { d: '2', hi: '', he: '' },
            { d: '3', hi: '', he: '' },
            { d: '4', hi: '', he: '' },
            { d: '5', hi: '', he: '' },
            { d: '6', hi: '', he: '' },
            { d: '7', hi: '', he: '' }
        ];
        var setReadOnly = false;
        vm.onchange = () => {
            vm.modelCtrl.$setViewValue(vm.JSONdata);
        }




        
        //vm.fileinput
        vm.$onInit =  () => {
            if ($element.attr('readonly')) {
                vm.setReadOnly = true;
            }
            vm.modelCtrl.$render = () => {
                vm.JSONdata = angular.merge({}, vm.JSONdata, vm.modelCtrl.$viewValue);
            };
        }

        vm.$onChanges = (changes) => {
        }
    }]
};

export default diaHoraEmpleadoComponent;
