window.GrebionTableSettings = {
        columnIndex: 0,
        componentId: null,
        
        init: function(componentId, initialColumnCount) {
            this.componentId = componentId;
            this.columnIndex = initialColumnCount || 0;
            this.bindEvents();
        },
        
        bindEvents: function() {
            var self = this;
            
            // Обработчик для всех кнопок с data-action
            document.addEventListener('click', function(e) {
                var action = e.target.getAttribute('data-action');
                var componentId = e.target.getAttribute('data-component-id');
                var index = e.target.getAttribute('data-index');
                
                if (!action || !componentId) return;
                
                switch (action) {
                    case 'add-column':
                        self.addColumn(componentId);
                        break;
                    case 'remove-column':
                        self.removeColumn(componentId, parseInt(index));
                        break;
                    case 'toggle-column':
                        self.toggleColumn(componentId, parseInt(index));
                        break;
                    case 'save-schema':
                        self.saveSchema(componentId);
                        break;
                }
            });
            
            // Обработчик для select схемы
            document.addEventListener('change', function(e) {
                var action = e.target.getAttribute('data-action');
                var componentId = e.target.getAttribute('data-component-id');
                
                if (action === 'schema-select' && componentId) {
                    self.onSchemaSelect(componentId, e.target.value);
                } else if (action === 'type-change' && componentId) {
                    var index = e.target.getAttribute('data-index');
                    self.onTypeChange(componentId, parseInt(index), e.target.value);
                }
            });
            
            // Автообновление схемы при изменении полей
            document.addEventListener('input', function(e) {
                if (e.target.matches('.column-code, .column-title, .column-sort, .column-options')) {
                    var componentId = e.target.closest('.grebion-table-settings').id;
                    self.updateSchemaJson(componentId);
                }
            });
        },
        
        onSchemaSelect: function(componentId, schemaId) {
            if (schemaId) {
                // Загружаем выбранную схему
                this.loadSchema(componentId, schemaId);
            } else {
                // Очищаем для создания новой схемы
                this.clearSchema(componentId);
            }
        },
        
        loadSchema: function(componentId, schemaId) {
            // Здесь можно добавить AJAX загрузку схемы
            // Пока используем данные из select
            var select = document.getElementById('schema-select-' + componentId);
            var option = select.querySelector('option[value="' + schemaId + '"]');
            
            if (option) {
                document.getElementById('schema-name-' + componentId).value = option.dataset.schemaName || '';
                document.getElementById('schema-description-' + componentId).value = option.dataset.schemaDescription || '';
            }
        },
        
        clearSchema: function(componentId) {
            document.getElementById('schema-name-' + componentId).value = '';
            document.getElementById('schema-description-' + componentId).value = '';
            document.getElementById('columns-container-' + componentId).innerHTML = '';
            this.columnIndex = 0;
            this.updateSchemaJson(componentId);
        },
        
        addColumn: function(componentId) {
            var container = document.getElementById('columns-container-' + componentId);
            var index = this.columnIndex++;
            
            var columnHtml = this.getColumnTemplate(componentId, index);
            container.insertAdjacentHTML('beforeend', columnHtml);
            this.updateSchemaJson(componentId);
        },
        
        removeColumn: function(componentId, index) {
            if (confirm('Удалить колонку?')) {
                var columnItem = document.querySelector('#columns-container-' + componentId + ' .grebion-column-item[data-index="' + index + '"]');
                if (columnItem) {
                    columnItem.remove();
                    this.updateSchemaJson(componentId);
                }
            }
        },
        
        toggleColumn: function(componentId, index) {
            var columnItem = document.querySelector('#columns-container-' + componentId + ' .grebion-column-item[data-index="' + index + '"]');
            var content = columnItem.querySelector('.grebion-column-content');
            
            if (content.style.display === 'none') {
                content.style.display = 'block';
            } else {
                content.style.display = 'none';
            }
        },
        
        onTypeChange: function(componentId, index, type) {
            var settingsContainer = document.getElementById('type-settings-' + componentId + '-' + index);
            
            if (type === 'select' || type === 'multiselect') {
                settingsContainer.innerHTML = `
                    <div class="ui-form-row">
                        <div class="ui-form-label">
                            <div class="ui-form-label-text">Варианты (по одному на строку):</div>
                        </div>
                        <div class="ui-form-content">
                            <div class="ui-ctl ui-ctl-textarea ui-ctl-w100">
                                <textarea class="ui-ctl-element column-options" 
                                          rows="3"
                                          placeholder="Вариант 1\nВариант 2\nВариант 3"></textarea>
                            </div>
                        </div>
                    </div>
                `;
            } else {
                settingsContainer.innerHTML = '';
            }
            
            this.updateSchemaJson(componentId);
        },
        
        getColumnTemplate: function(componentId, index) {
            return `
                <div class="grebion-column-item" data-index="${index}">
                    <div class="grebion-column-header">
                        <div class="grebion-column-drag">
                            <span class="ui-icon-set --move"></span>
                        </div>
                        <div class="grebion-column-title">
                            <strong>Новая колонка</strong>
                            <span class="grebion-column-type-badge">Текст</span>
                        </div>
                        <div class="grebion-column-actions">
                            <button type="button" 
                                    class="ui-btn ui-btn-sm ui-btn-light ui-btn-icon-edit"
                                    data-component-id="${componentId}"
                                    data-action="toggle-column"
                                    data-index="${index}"
                                    title="Редактировать">
                            </button>
                            <button type="button" 
                                    class="ui-btn ui-btn-sm ui-btn-danger ui-btn-icon-remove"
                                    data-component-id="${componentId}"
                                    data-action="remove-column"
                                    data-index="${index}"
                                    title="Удалить">
                            </button>
                        </div>
                    </div>
                    
                    <div class="grebion-column-content" style="display: block;">
                        <div class="ui-form-row-group">
                            <div class="ui-form-row">
                                <div class="ui-form-label">
                                    <div class="ui-form-label-text">Код колонки:</div>
                                </div>
                                <div class="ui-form-content">
                                    <div class="ui-ctl ui-ctl-textbox ui-ctl-w100">
                                        <input type="text" 
                                               class="ui-ctl-element column-code" 
                                               placeholder="column_code"
                                               required>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="ui-form-row">
                                <div class="ui-form-label">
                                    <div class="ui-form-label-text">Название:</div>
                                </div>
                                <div class="ui-form-content">
                                    <div class="ui-ctl ui-ctl-textbox ui-ctl-w100">
                                        <input type="text" 
                                               class="ui-ctl-element column-title" 
                                               placeholder="Название колонки"
                                               required>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="ui-form-row">
                                <div class="ui-form-label">
                                    <div class="ui-form-label-text">Тип:</div>
                                </div>
                                <div class="ui-form-content">
                                    <div class="ui-ctl ui-ctl-after-icon ui-ctl-dropdown">
                                        <div class="ui-ctl-after ui-ctl-icon-angle"></div>
                                        <select class="ui-ctl-element column-type" 
                                                 data-component-id="${componentId}"
                                                 data-action="type-change"
                                                 data-index="${index}">
                                             ${this.getColumnTypeOptions()}
                                         </select>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="ui-form-row">
                                <div class="ui-form-label">
                                    <div class="ui-form-label-text">Сортировка:</div>
                                </div>
                                <div class="ui-form-content">
                                    <div class="ui-ctl ui-ctl-textbox">
                                        <input type="number" 
                                               class="ui-ctl-element column-sort" 
                                               value="${(index + 1) * 100}"
                                               min="0">
                                    </div>
                                </div>
                            </div>
                            
                            <div class="grebion-column-type-settings" id="type-settings-${componentId}-${index}">
                            </div>
                        </div>
                    </div>
                </div>
            `;
        },
         
         getColumnTypeOptions: function() {
             var options = '';
             if (window.grebionColumnTypes) {
                 Object.keys(window.grebionColumnTypes).forEach(function(typeCode) {
                     options += '<option value="' + typeCode + '">' + window.grebionColumnTypes[typeCode] + '</option>';
                 });
             }
             return options;
         },
         
         saveSchema: function(componentId) {
            var schemaName = document.getElementById('schema-name-' + componentId).value;
            var schemaDescription = document.getElementById('schema-description-' + componentId).value;
            var schemaJson = document.getElementById('schema-json-' + componentId).value;
            
            if (!schemaName) {
                alert('Введите название схемы');
                return;
            }
            
            // Отправляем данные через стандартный AJAX Bitrix
            BX.ajax.runComponentAction('grebion:table.settings', 'saveSchema', {
                mode: 'class',
                data: {
                    schemaData: schemaJson,
                    schemaName: schemaName,
                    schemaDescription: schemaDescription,
                    sessid: BX.bitrix_sessid()
                }
            }).then(function(response) {
                if (response.data) {
                    alert('Схема успешно сохранена');
                    // Обновляем список схем если нужно
                    if (response.data.schema_id) {
                        var select = document.getElementById('schema-select-' + componentId);
                        var option = select.querySelector('option[value="' + response.data.schema_id + '"]');
                        if (!option) {
                            option = document.createElement('option');
                            option.value = response.data.schema_id;
                            option.textContent = schemaName;
                            select.appendChild(option);
                        }
                        select.value = response.data.schema_id;
                    }
                } else {
                    // Проверяем ошибки в поле status
                    var errorMessage = 'Неизвестная ошибка';
                    if (response.status && response.status.length > 0) {
                        errorMessage = response.status[0].message;
                    } else if (response.errors && response.errors.length > 0) {
                        errorMessage = response.errors[0].message;
                    }
                    alert('Ошибка: ' + errorMessage);
                }
            }).catch(function(response) {
                var errorMessage = 'Ошибка соединения с сервером';
                if (response.status && response.status.length > 0) {
                    errorMessage = response.status[0].message;
                } else if (response.errors && response.errors.length > 0) {
                    errorMessage = response.errors[0].message;
                }
                alert('Ошибка: ' + errorMessage);
            });
        },
        
        updateSchemaJson: function(componentId) {
            var container = document.getElementById('columns-container-' + componentId);
            var columns = container.querySelectorAll('.grebion-column-item');
            var schema = [];
            
            columns.forEach(function(column, index) {
                var code = column.querySelector('.column-code').value;
                var title = column.querySelector('.column-title').value;
                var type = column.querySelector('.column-type').value;
                var sort = column.querySelector('.column-sort').value;
                
                var columnData = {
                    code: code,
                    title: title,
                    type: type,
                    sort: parseInt(sort) || (index + 1) * 100
                };
                
                var optionsTextarea = column.querySelector('.column-options');
                if (optionsTextarea && optionsTextarea.value) {
                    columnData.options = optionsTextarea.value.split('\n').filter(function(option) {
                        return option.trim() !== '';
                    });
                }
                
                schema.push(columnData);
                
                // Обновляем заголовок колонки
                var titleElement = column.querySelector('.grebion-column-title strong');
                var typeBadge = column.querySelector('.grebion-column-type-badge');
                if (titleElement) titleElement.textContent = title || 'Колонка ' + (index + 1);
                if (typeBadge) {
                    var typeSelect = column.querySelector('.column-type');
                    var selectedOption = typeSelect.options[typeSelect.selectedIndex];
                    if (selectedOption) {
                        typeBadge.textContent = selectedOption.text;
                    }
                }
            });
            
            document.getElementById('schema-json-' + componentId).value = JSON.stringify(schema);
        }
    };