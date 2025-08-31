/**
 * Компонент управления настройками таблиц
 * @namespace GrebionTableSettings
 */
window.GrebionTableSettings = window.GrebionTableSettings || {
    
    /**
     * Инициализация компонента
     * @param {string} componentId - ID компонента
     * @param {number} initialSchemaId - Начальный ID схемы
     * @param {Object} columnTypes - Типы колонок
     */
    init: function(componentId, initialSchemaId, columnTypes) {
        this.componentId = componentId;
        this.columnTypes = columnTypes || {};
        
        const select = document.getElementById(componentId + '-schema-select');
        
        if (select) {
            select.addEventListener('change', function() {
                GrebionTableSettings.onSchemaSelect(componentId, this.value);
            });
        }
        
        // Если есть начальная схема - загружаем её через AJAX
        if (initialSchemaId > 0) {
            this.loadSchemaData(componentId, initialSchemaId);
        }
    },
    
    /**
     * Переключение схем
     * @param {string} componentId - ID компонента
     * @param {string} schemaId - ID выбранной схемы
     */
    onSchemaSelect: function(componentId, schemaId) {
        const hiddenInput = document.getElementById(componentId + '-schema-id');
        const newForm = document.getElementById(componentId + '-new-form');
        const saveBtn = document.getElementById(componentId + '-save-btn');
        
        hiddenInput.value = schemaId;
        
        if (schemaId === '0') {
            // Не выбрана схема - скрываем форму
            newForm.style.display = 'none';
        } else {
            // Выбрана существующая схема - загружаем её данные
            this.loadSchemaData(componentId, schemaId);
            newForm.style.display = 'block';
            saveBtn.textContent = 'Обновить схему';
        }
    },
    
    /**
     * Загрузка данных схемы по ID
     * @param {string} componentId - ID компонента
     * @param {number} schemaId - ID схемы
     */
    loadSchemaData: function(componentId, schemaId) {
        // AJAX-запрос для загрузки схемы
        BX.ajax.runComponentAction('grebion:table.settings', 'loadSchema', {
            mode: 'class',
            data: {
                schemaId: parseInt(schemaId)
            }
        }).then(function(response) {
            if (response.data) {
                const schema = response.data;
                
                // Заполняем основные поля
                const nameInput = document.getElementById(componentId + '-schema-name');
                const descInput = document.getElementById(componentId + '-schema-desc');
                
                if (nameInput) nameInput.value = schema.NAME || '';
                if (descInput) descInput.value = schema.DESCRIPTION || '';
                
                // Полностью очищаем контейнер колонок
                const container = document.getElementById(componentId + '-columns');
                container.innerHTML = '';
                
                // Заполняем новые колонки (сортируем по полю sort)
                if (schema.COLUMNS && schema.COLUMNS.length > 0) {
                    const sortedColumns = schema.COLUMNS.sort(function(a, b) {
                        const sortA = parseInt(a.SORT || a.sort) || 0;
                        const sortB = parseInt(b.SORT || b.sort) || 0;
                        return sortA - sortB;
                    });
                    
                    sortedColumns.forEach(function(column, index) {
                        const columnHtml = GrebionTableSettings.getColumnTemplate(componentId, index, column);
                        container.insertAdjacentHTML('beforeend', columnHtml);
                    });
                }
            }
        }).catch(function(response) {
            let errorMessage = 'Ошибка загрузки схемы';
            if (response.errors && response.errors.length > 0) {
                errorMessage = response.errors[0].message || errorMessage;
            }
            alert('Ошибка: ' + errorMessage);
        });
    },
    
    /**
     * Создание новой схемы
     * @param {string} componentId - ID компонента
     */
    createNewSchema: function(componentId) {
        const hiddenInput = document.getElementById(componentId + '-schema-id');
        const newForm = document.getElementById(componentId + '-new-form');
        const saveBtn = document.getElementById(componentId + '-save-btn');
        const select = document.getElementById(componentId + '-schema-select');
        
        // Сбрасываем выбор в селекте
        select.value = '0';
        
        // Очищаем форму
        this.clearNewSchemaForm(componentId);
        
        // Показываем форму
        newForm.style.display = 'block';
        
        // Меняем текст кнопки
        saveBtn.textContent = 'Сохранить схему';
        
        // Сбрасываем hidden input
        hiddenInput.value = '0';
    },
    
    /**
     * Добавление новой колонки
     * @param {string} componentId - ID компонента
     */
    addColumn: function(componentId) {
        const container = document.getElementById(componentId + '-columns');
        const index = container.children.length;
        
        const columnHtml = this.getColumnTemplate(componentId, index);
        container.insertAdjacentHTML('beforeend', columnHtml);
    },
    
    /**
     * Генерация HTML шаблона колонки
     * @param {string} componentId - ID компонента
     * @param {number} index - Индекс колонки
     * @param {Object} columnData - Данные колонки
     * @returns {string} HTML шаблон
     */
    getColumnTemplate: function(componentId, index, columnData) {
        columnData = columnData || {};
        
        const typeOptions = Object.entries(this.columnTypes)
            .map(([code, name]) => {
                const selectedType = columnData.TYPE || columnData.type || '';
                const selected = (selectedType === code) ? 'selected' : '';
                return `<option value="${code}" ${selected}>${name}</option>`;
            })
            .join('');
            
        return `
            <div class="grebion-column-item" data-index="${index}">
                <div class="grebion-column-header">
                    <span>Колонка ${index + 1}</span>
                    <button type="button" class="btn btn-sm btn-danger" 
                            onclick="GrebionTableSettings.removeColumn('${componentId}', ${index})">
                        Удалить
                    </button>
                </div>
                <div class="grebion-column-fields">
                    <div class="form-group">
                        <label>Код:</label>
                        <input type="text" class="form-control column-code" 
                               placeholder="column_${index + 1}" 
                               value="${columnData.CODE || columnData.code || ''}" required>
                    </div>
                    <div class="form-group">
                        <label>Название:</label>
                        <input type="text" class="form-control column-title" 
                               placeholder="Колонка ${index + 1}" 
                               value="${columnData.TITLE || columnData.title || ''}" required>
                    </div>
                    <div class="form-group">
                        <label>Тип:</label>
                        <select class="form-control column-type" onchange="GrebionTableSettings.onTypeChange(this, ${index})">
                            ${typeOptions}
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Сортировка:</label>
                        <input type="number" class="form-control column-sort" 
                               value="${columnData.SORT || columnData.sort || ((index + 1) * 100)}" min="0">
                    </div>
                </div>
                
                <!-- Дополнительные настройки для select/multiselect -->
                <div class="grebion-column-options" style="display: ${((columnData.TYPE || columnData.type) === 'select' || (columnData.TYPE || columnData.type) === 'multiselect') ? 'block' : 'none'}">
                    <div class="form-group">
                        <label>Варианты списка (по одному на строку):</label>
                        <textarea class="form-control column-options" rows="4" placeholder="Вариант 1\nВариант 2\nВариант 3">${((columnData.OPTIONS || columnData.options) || []).join('\n')}</textarea>
                        <small class="form-text text-muted">Введите каждый вариант с новой строки</small>
                    </div>
                </div>
            </div>
        `;
    },
    
    /**
     * Обработчик изменения типа колонки
     * @param {HTMLElement} selectElement - Элемент select
     * @param {number} index - Индекс колонки
     */
    onTypeChange: function(selectElement, index) {
        const columnItem = selectElement.closest('.grebion-column-item');
        const optionsContainer = columnItem.querySelector('.grebion-column-options');
        const selectedType = selectElement.value;
        
        if (selectedType === 'select' || selectedType === 'multiselect') {
            optionsContainer.style.display = 'block';
        } else {
            optionsContainer.style.display = 'none';
        }
    },
    
    /**
     * Удаление колонки
     * @param {string} componentId - ID компонента
     * @param {number} index - Индекс колонки
     */
    removeColumn: function(componentId, index) {
        const container = document.getElementById(componentId + '-columns');
        const item = container.querySelector(`[data-index="${index}"]`);
        if (item) {
            item.remove();
        }
    },
    
    /**
     * Сохранение схемы
     * @param {string} componentId - ID компонента
     */
    saveSchema: function(componentId) {
        const nameInput = document.getElementById(componentId + '-schema-name');
        const descInput = document.getElementById(componentId + '-schema-desc');
        const container = document.getElementById(componentId + '-columns');
        
        const schemaName = nameInput.value.trim();
        if (!schemaName) {
            alert('Введите название схемы');
            return;
        }
        
        const columns = [];
        const columnItems = container.querySelectorAll('.grebion-column-item');
        
        // Проверка на дублирующиеся коды и названия перед отправкой
        const usedCodes = [];
        const usedTitles = [];
        let hasErrors = false;
        
        columnItems.forEach((item, index) => {
            const code = item.querySelector('.column-code').value.trim();
            const title = item.querySelector('.column-title').value.trim();
            const type = item.querySelector('.column-type').value;
            const sort = parseInt(item.querySelector('.column-sort').value) || ((index + 1) * 100);
            
            if (code && title) {
                // Проверка на дублирующиеся коды
                if (usedCodes.includes(code)) {
                    alert('Код колонки "' + code + '" уже используется');
                    hasErrors = true;
                    return;
                }
                usedCodes.push(code);
                
                // Проверка на дублирующиеся названия
                if (usedTitles.includes(title)) {
                    alert('Название колонки "' + title + '" уже используется');
                    hasErrors = true;
                    return;
                }
                usedTitles.push(title);
                
                // Формируем данные колонки
                const columnData = { code, title, type, sort };
                
                // Для select/multiselect добавляем варианты
                if (type === 'select' || type === 'multiselect') {
                    const optionsTextarea = item.querySelector('.column-options');
                    if (optionsTextarea) {
                        const optionsText = optionsTextarea.value.trim();
                        if (optionsText) {
                            columnData.options = optionsText.split('\n')
                                .map(option => option.trim())
                                .filter(option => option.length > 0);
                        } else {
                            alert('Для типа "' + (type === 'select' ? 'Список' : 'Множественный список') + '" необходимо указать варианты');
                            hasErrors = true;
                            return;
                        }
                    }
                }
                
                columns.push(columnData);
            }
        });
        
        if (hasErrors) {
            return;
        }
        
        if (columns.length === 0) {
            alert('Добавьте хотя бы одну колонку');
            return;
        }
        
        // Получаем текущий ID схемы для определения - создавать новую или обновлять существующую
        const hiddenInput = document.getElementById(componentId + '-schema-id');
        const currentSchemaId = parseInt(hiddenInput.value) || 0;
        
        // AJAX-запрос для сохранения схемы
        BX.ajax.runComponentAction('grebion:table.settings', 'saveSchema', {
            mode: 'class',
            data: {
                schemaName: schemaName,
                schemaDescription: descInput.value.trim(),
                columns: columns,
                schemaId: currentSchemaId
            }
        }).then(function(response) {
            if (response.data && response.data.ID) {
                const newSchemaId = response.data.ID;
                const action = response.data.ACTION;
                
                // Обновляем скрытое поле с ID схемы
                const hiddenInput = document.getElementById(componentId + '-schema-id');
                hiddenInput.value = newSchemaId;
                
                const select = document.getElementById(componentId + '-schema-select');
                
                if (action === 'CREATED') {
                    // Добавляем новую схему в селект
                    const option = document.createElement('option');
                    option.value = newSchemaId;
                    option.textContent = schemaName;
                    option.selected = true;
                    select.appendChild(option);
                    
                    alert('Схема успешно создана');
                } else if (action === 'UPDATED') {
                    // Обновляем название в селекте
                    const selectedOption = select.querySelector(`option[value="${newSchemaId}"]`);
                    if (selectedOption) {
                        selectedOption.textContent = schemaName;
                    }
                    
                    alert('Схема успешно обновлена');
                }
                
                // Обновляем текст кнопки на "Обновить схему"
                const saveBtn = document.getElementById(componentId + '-save-btn');
                saveBtn.textContent = 'Обновить схему';
            }
        }).catch(function(response) {
            let errorMessage = 'Неизвестная ошибка';
            if (response.errors && response.errors.length > 0) {
                const error = response.errors[0];
                switch(error.code) {
                    case 'DUPLICATE_CODE':
                        errorMessage = 'Код колонки "' + (error.customData?.CODE || '') + '" уже используется';
                        break;
                    case 'DUPLICATE_TITLE':
                        errorMessage = 'Название колонки "' + (error.customData?.TITLE || '') + '" уже используется';
                        break;
                    default:
                        errorMessage = error.message || 'Ошибка при сохранении схемы';
                }
            }
            alert('Ошибка: ' + errorMessage);
        });
    },
    
    /**
     * Очистка формы создания новой схемы
     * @param {string} componentId - ID компонента
     */
    clearNewSchemaForm: function(componentId) {
        const nameInput = document.getElementById(componentId + '-schema-name');
        const descInput = document.getElementById(componentId + '-schema-desc');
        const container = document.getElementById(componentId + '-columns');
        
        if (nameInput) nameInput.value = '';
        if (descInput) descInput.value = '';
        if (container) container.innerHTML = '';
    }
};
