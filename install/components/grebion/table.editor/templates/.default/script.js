/**
 * Компонент редактора таблиц
 * @namespace GrebionTableEditor
 */
window.GrebionTableEditor = window.GrebionTableEditor || {
    
    /**
     * Инициализация компонента
     * @param {string} componentId - ID компонента
     * @param {number} schemaId - ID схемы
     * @param {number} tableId - ID таблицы
     * @param {Object} schema - Схема таблицы
     * @param {Object} fieldTypes - Типы полей
     */
    init: function(componentId, schemaId, tableId, schema, fieldTypes) {
        this.componentId = componentId;
        this.schemaId = schemaId;
        this.tableId = tableId;
        this.schema = schema || {};
        this.fieldTypes = fieldTypes || {};
        this.rowCounter = 0;
        
        // Подсчитываем существующие строки
        const tbody = document.getElementById(componentId + '-tbody');
        if (tbody) {
            this.rowCounter = tbody.querySelectorAll('tr').length;
        }
        
        console.log('TableEditor initialized:', {
            componentId: componentId,
            schemaId: schemaId,
            tableId: tableId,
            schema: schema,
            rowCounter: this.rowCounter
        });
    },
    
    /**
     * Добавление новой строки
     * @param {string} componentId - ID компонента
     */
    addRow: function(componentId) {
        const tbody = document.getElementById(componentId + '-tbody');
        if (!tbody) {
            console.error('Table body not found:', componentId);
            return;
        }
        
        const schema = this.schema;
        if (!schema.COLUMNS || schema.COLUMNS.length === 0) {
            console.error('No columns in schema');
            return;
        }
        
        const rowIndex = this.rowCounter++;
        const row = document.createElement('tr');
        row.setAttribute('data-row-index', rowIndex);
        
        // Создаём ячейки для каждой колонки
        schema.COLUMNS.forEach(column => {
            const cell = document.createElement('td');
            cell.innerHTML = this.renderFieldHtml(column, '', componentId, rowIndex);
            row.appendChild(cell);
        });
        
        // Добавляем ячейку с действиями
        const actionsCell = document.createElement('td');
        actionsCell.className = 'grebion-row-actions';
        actionsCell.innerHTML = `
            <button type="button" class="btn btn-danger btn-sm" 
                    onclick="GrebionTableEditor.removeRow('${componentId}', ${rowIndex})">
                Удалить
            </button>
        `;
        row.appendChild(actionsCell);
        
        tbody.appendChild(row);
        
        console.log('Row added:', rowIndex);
    },
    
    /**
     * Удаление строки
     * @param {string} componentId - ID компонента
     * @param {number} rowIndex - Индекс строки
     */
    removeRow: function(componentId, rowIndex) {
        const tbody = document.getElementById(componentId + '-tbody');
        if (!tbody) {
            console.error('Table body not found:', componentId);
            return;
        }
        
        const row = tbody.querySelector(`tr[data-row-index="${rowIndex}"]`);
        if (row) {
            row.remove();
            console.log('Row removed:', rowIndex);
        }
    },
    
    /**
     * Генерация HTML для поля
     * @param {Object} column - Описание колонки
     * @param {string} value - Значение поля
     * @param {string} componentId - ID компонента
     * @param {number} rowIndex - Индекс строки
     * @returns {string} HTML поля
     */
    renderFieldHtml: function(column, value, componentId, rowIndex) {
        const fieldName = componentId + '-field-' + column.code + '-' + rowIndex;
        const fieldId = fieldName;
        
        switch (column.type) {
            case 'text':
            case 'email':
            case 'url':
            case 'phone':
                return `<input type="${column.type === 'text' ? 'text' : column.type}" 
                               class="grebion-cell-input" 
                               name="${fieldName}" 
                               id="${fieldId}"
                               value="${this.escapeHtml(value)}">`;
                               
            case 'number':
                return `<input type="number" 
                               class="grebion-cell-input" 
                               name="${fieldName}" 
                               id="${fieldId}"
                               value="${this.escapeHtml(value)}">`;
                               
            case 'date':
                return `<input type="date" 
                               class="grebion-cell-input" 
                               name="${fieldName}" 
                               id="${fieldId}"
                               value="${this.escapeHtml(value)}">`;
                               
            case 'datetime':
                return `<input type="datetime-local" 
                               class="grebion-cell-input" 
                               name="${fieldName}" 
                               id="${fieldId}"
                               value="${this.escapeHtml(value)}">`;
                               
            case 'boolean':
                const checked = value ? 'checked' : '';
                return `<input type="checkbox" 
                               class="grebion-cell-checkbox" 
                               name="${fieldName}" 
                               id="${fieldId}"
                               value="1" ${checked}>`;
                               
            case 'select':
                const options = column.options || [];
                let html = `<select class="grebion-cell-select" name="${fieldName}" id="${fieldId}">`;
                html += '<option value="">Выберите...</option>';
                options.forEach(option => {
                    const selected = (value === option) ? 'selected' : '';
                    html += `<option value="${this.escapeHtml(option)}" ${selected}>${this.escapeHtml(option)}</option>`;
                });
                html += '</select>';
                return html;
                
            case 'multiselect':
                const multiselectOptions = column.options || [];
                const selectedValues = Array.isArray(value) ? value : (value ? value.split(',') : []);
                let multiselectHtml = `<select class="grebion-cell-select" name="${fieldName}[]" id="${fieldId}" multiple size="3">`;
                multiselectOptions.forEach(option => {
                    const selected = selectedValues.includes(option) ? 'selected' : '';
                    multiselectHtml += `<option value="${this.escapeHtml(option)}" ${selected}>${this.escapeHtml(option)}</option>`;
                });
                multiselectHtml += '</select>';
                return multiselectHtml;
                
            case 'file':
                return `<input type="file" 
                               class="grebion-cell-input" 
                               name="${fieldName}" 
                               id="${fieldId}">`;
                               
            default:
                return `<textarea class="grebion-cell-input" 
                                 name="${fieldName}" 
                                 id="${fieldId}" 
                                 rows="2">${this.escapeHtml(value)}</textarea>`;
        }
    },
    
    /**
     * Сохранение таблицы
     * @param {string} componentId - ID компонента
     */
    saveTable: function(componentId) {
        const tbody = document.getElementById(componentId + '-tbody');
        if (!tbody) {
            alert('Таблица не найдена');
            return;
        }
        
        const rows = this.collectTableData(componentId);
        if (rows.length === 0) {
            alert('Добавьте хотя бы одну строку данных');
            return;
        }
        
        console.log('Saving table:', {
            rows: rows,
            tableId: this.tableId,
            schemaId: this.schemaId
        });
        
        // AJAX-запрос для сохранения таблицы
        BX.ajax.runComponentAction('grebion:table.editor', 'saveTable', {
            mode: 'class',
            data: {
                rows: rows,
                tableId: this.tableId,
                schemaId: this.schemaId
            }
        }).then((response) => {
            console.log('Save response:', response);
            if (response.data && response.data.ID) {
                const newTableId = response.data.ID;
                const action = response.data.ACTION;
                
                // Обновляем скрытое поле с ID таблицы
                const hiddenInput = document.getElementById(componentId + '-table-id');
                if (hiddenInput) {
                    hiddenInput.value = newTableId;
                }
                
                // Обновляем внутренний tableId
                this.tableId = newTableId;
                
                if (action === 'CREATED') {
                    alert('Таблица успешно создана');
                } else if (action === 'UPDATED') {
                    alert('Таблица успешно обновлена');
                }
            }
        }).catch((response) => {
            let errorMessage = 'Неизвестная ошибка при сохранении';
            if (response.errors && response.errors.length > 0) {
                errorMessage = response.errors[0].message || errorMessage;
            }
            alert('Ошибка: ' + errorMessage);
            console.error('Save error:', response);
        });
    },
    
    /**
     * Сбор данных из таблицы
     * @param {string} componentId - ID компонента
     * @returns {Array} Массив строк с данными
     */
    collectTableData: function(componentId) {
        const tbody = document.getElementById(componentId + '-tbody');
        if (!tbody) {
            return [];
        }
        
        const rows = [];
        const tableRows = tbody.querySelectorAll('tr');
        
        tableRows.forEach((tr, rowIndex) => {
            const rowData = {};
            
            this.schema.COLUMNS.forEach((column, colIndex) => {
                const fieldName = componentId + '-field-' + column.code + '-' + tr.getAttribute('data-row-index');
                const field = document.getElementById(fieldName) || document.getElementsByName(fieldName)[0];
                
                if (field) {
                    if (column.type === 'boolean') {
                        rowData[column.code] = field.checked;
                    } else if (column.type === 'multiselect') {
                        const selectedOptions = Array.from(field.selectedOptions).map(option => option.value);
                        rowData[column.code] = selectedOptions;
                    } else {
                        rowData[column.code] = field.value;
                    }
                }
            });
            
            rows.push(rowData);
        });
        
        console.log('Collected table data:', rows);
        return rows;
    },
    
    /**
     * Экранирование HTML
     * @param {string} text - Текст для экранирования
     * @returns {string} Экранированный текст
     */
    escapeHtml: function(text) {
        if (typeof text !== 'string') {
            return text;
        }
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    },
    
    /**
     * Загрузка данных таблицы
     * @param {string} componentId - ID компонента
     * @param {number} tableId - ID таблицы
     */
    loadTable: function(componentId, tableId) {
        console.log('Loading table:', tableId);
        
        // AJAX-запрос для загрузки таблицы
        BX.ajax.runComponentAction('grebion:table.editor', 'loadTable', {
            mode: 'class',
            data: {
                tableId: tableId
            }
        }).then((response) => {
            console.log('Load response:', response);
            if (response.data) {
                this.renderTableData(componentId, response.data);
            }
        }).catch((response) => {
            let errorMessage = 'Ошибка загрузки таблицы';
            if (response.errors && response.errors.length > 0) {
                errorMessage = response.errors[0].message || errorMessage;
            }
            alert('Ошибка: ' + errorMessage);
            console.error('Load error:', response);
        });
    },
    
    /**
     * Отрисовка данных таблицы
     * @param {string} componentId - ID компонента
     * @param {Object} tableData - Данные таблицы
     */
    renderTableData: function(componentId, tableData) {
        console.log('Rendering table data:', tableData);
        
        // Обновляем название таблицы
        const tableNameInput = document.getElementById(componentId + '-table-name');
        if (tableNameInput) {
            tableNameInput.value = tableData.NAME || '';
        }
        
        // Очищаем существующие строки
        const tbody = document.getElementById(componentId + '-tbody');
        if (tbody) {
            tbody.innerHTML = '';
        }
        
        // Добавляем строки с данными
        if (tableData.ROWS && tableData.ROWS.length > 0) {
            tableData.ROWS.forEach((rowData, index) => {
                this.addRowWithData(componentId, rowData.DATA, index);
            });
        }
        
        this.rowCounter = tableData.ROWS ? tableData.ROWS.length : 0;
    },
    
    /**
     * Добавление строки с данными
     * @param {string} componentId - ID компонента
     * @param {Object} data - Данные строки
     * @param {number} rowIndex - Индекс строки
     */
    addRowWithData: function(componentId, data, rowIndex) {
        const tbody = document.getElementById(componentId + '-tbody');
        if (!tbody) {
            return;
        }
        
        const row = document.createElement('tr');
        row.setAttribute('data-row-index', rowIndex);
        
        // Создаём ячейки для каждой колонки
        this.schema.COLUMNS.forEach(column => {
            const cell = document.createElement('td');
            const fieldValue = data[column.code] || '';
            cell.innerHTML = this.renderFieldHtml(column, fieldValue, componentId, rowIndex);
            row.appendChild(cell);
        });
        
        // Добавляем ячейку с действиями
        const actionsCell = document.createElement('td');
        actionsCell.className = 'grebion-row-actions';
        actionsCell.innerHTML = `
            <button type="button" class="btn btn-danger btn-sm" 
                    onclick="GrebionTableEditor.removeRow('${componentId}', ${rowIndex})">
                Удалить
            </button>
        `;
        row.appendChild(actionsCell);
        
        tbody.appendChild(row);
    }
};
