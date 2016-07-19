function gtIblockFormFieldsController(arParams) {
    "use strict";


    var data = {};
    try {
        data = JSON.parse(arParams.data);
        BX.loadCSS(data.additionalCss);
    } catch (e) {
    }

    if (!String.prototype.trim) {
        String.prototype.trim = function() {
            return this.replace(/^[\s\uFEFF\xA0]+|[\s\uFEFF\xA0]+$/g, '');
        };
    }

    function addOption(table, item) {
        var row = document.createElement('tr');
        row.className = 'gt-iblock-form-table-row';
        var column = document.createElement('td');


        var select = document.createElement('select');
        select.className = 'js-iblock-form-property-code';
        select.onchange = updateParameterValue;
        var option = document.createElement('option');
        option.value = '';
        select.appendChild(option);
        if (typeof data.availFields === 'object') {
            data.availFields.forEach(function(f, i) {
                option = document.createElement('option');
                option.value = f.CODE;
                option.innerHTML = f.NAME;
                option.selected = (f.CODE === item.CODE);
                select.appendChild(option);
            });
        }
        column.appendChild(select);
        row.appendChild(column);

        column = document.createElement('td');
        input = document.createElement('input');
        input.type = 'text';
        input.className = 'js-iblock-form-field-name';
        input.size = 15;
        input.value = item.FIELD_NAME || item.CODE || '';
        input.onchange = updateParameterValue;
        column.appendChild(input);
        row.appendChild(column);

        column = document.createElement('td');
        input = document.createElement('input');
        input.type = 'checkbox';
        input.className = 'js-iblock-form-field-required';
        input.value = 'Y';
        input.checked = (item.REQUIRED === 'Y');
        input.onchange = updateParameterValue;
        column.appendChild(input);
        row.appendChild(column);

        table.appendChild(row);
    }

    function updateParameterValue() {
        var data = [];
        var rows = document.querySelectorAll('.gt-iblock-form-table-row');
        rows.forEach(function(row) {
            var item = {};
            var input = row.querySelector('.js-iblock-form-property-code');
            if (input.value.trim().length === 0) {
                return true;
            }
            item.CODE = input.value.trim();

            input = row.querySelector('.js-iblock-form-field-name');
            item.FIELD_NAME = input.value.trim();

            input = row.querySelector('.js-iblock-form-field-required');
            item.REQUIRED = (input.checked ? 'Y' : 'N');

            data.push(item);
        });
        arParams.oInput.value = JSON.stringify(data);
    }

    var table, row, column, input;
    table = document.createElement('table');
    table.className = 'gt-iblock-form-table';

    row = document.createElement('tr');

    column = document.createElement('th');
    column.innerHTML = 'Код';
    row.appendChild(column);

    column = document.createElement('th');
    column.innerHTML = 'Имя поля';
    row.appendChild(column);

    column = document.createElement('th');
    column.innerHTML = 'Обяз.';
    row.appendChild(column);

    table.appendChild(row);

    var value = [];
    try {
        value = JSON.parse(arParams.oInput.value);
    } catch (e) {
    }
    if (typeof value === 'object' && value.length > 0) {
        value.forEach(function(item, i) {
            addOption(table, item);
        });
    }
    addOption(table, {});

    arParams.oCont.appendChild(table);

    var button = document.createElement('input');
    button.type = 'button';
    button.value = 'Еще...';
    button.className = 'gt-iblock-form-button';
    button.onclick = function(e) {
        e.preventDefault();
        addOption(table, {});
    };
    arParams.oCont.appendChild(button);

    arParams.oInput.type = 'hidden';
}