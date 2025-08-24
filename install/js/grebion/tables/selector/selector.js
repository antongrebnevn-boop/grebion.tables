/**
 * Селектор таблиц для UF-типа
 */
window.GrebionTableSelector = {
    /**
     * Открыть окно выбора таблицы
     * @param {string} inputId ID поля ввода
     */
    open: function(inputId) {
        var dialog = new BX.PopupWindow('grebion-table-selector', null, {
            width: 600,
            height: 400,
            resizable: true,
            titleBar: BX.message('GREBION_TABLES_SELECTOR_TITLE') || 'Выбор таблицы',
            content: '<div id="grebion-table-list">Загрузка...</div>',
            buttons: [
                new BX.PopupWindowButton({
                    text: BX.message('JS_CORE_WINDOW_CLOSE') || 'Закрыть',
                    className: 'popup-window-button-decline',
                    events: {
                        click: function() {
                            dialog.close();
                        }
                    }
                })
            ]
        });
        
        dialog.show();
        
        // Загружаем список таблиц
        this.loadTableList(inputId, dialog);
    },
    
    /**
     * Очистить выбранную таблицу
     * @param {string} inputId ID поля ввода
     */
    clear: function(inputId) {
        var input = BX(inputId);
        var titleInput = input.parentNode.querySelector('.grebion-table-title');
        
        if (input) {
            input.value = '0';
        }
        
        if (titleInput) {
            titleInput.value = '';
        }
    },
    
    /**
     * Загрузить список таблиц
     * @param {string} inputId ID поля ввода
     * @param {BX.PopupWindow} dialog Диалог
     */
    loadTableList: function(inputId, dialog) {
        BX.ajax({
            url: '/bitrix/admin/grebion_table_ajax.php',
            data: {
                action: 'get_tables_list',
                sessid: BX.bitrix_sessid()
            },
            method: 'POST',
            dataType: 'json',
            onsuccess: function(data) {
                if (data.status === 'success') {
                    GrebionTableSelector.renderTableList(data.tables, inputId, dialog);
                } else {
                    BX('grebion-table-list').innerHTML = '<div class="adm-info-message-wrap adm-info-message-red">' +
                        '<div class="adm-info-message">' + (data.message || 'Ошибка загрузки') + '</div>' +
                        '</div>';
                }
            },
            onfailure: function() {
                BX('grebion-table-list').innerHTML = '<div class="adm-info-message-wrap adm-info-message-red">' +
                    '<div class="adm-info-message">Ошибка соединения</div>' +
                    '</div>';
            }
        });
    },
    
    /**
     * Отрендерить список таблиц
     * @param {Array} tables Массив таблиц
     * @param {string} inputId ID поля ввода
     * @param {BX.PopupWindow} dialog Диалог
     */
    renderTableList: function(tables, inputId, dialog) {
        var html = '<div class="grebion-table-selector-list">';
        
        if (tables.length === 0) {
            html += '<div class="adm-info-message-wrap">' +
                '<div class="adm-info-message">Таблицы не найдены</div>' +
                '</div>';
        } else {
            html += '<table class="adm-list-table">' +
                '<thead>' +
                '<tr class="adm-list-table-header">' +
                '<td class="adm-list-table-cell">ID</td>' +
                '<td class="adm-list-table-cell">Название</td>' +
                '<td class="adm-list-table-cell">Владелец</td>' +
                '<td class="adm-list-table-cell">Действие</td>' +
                '</tr>' +
                '</thead>' +
                '<tbody>';
            
            for (var i = 0; i < tables.length; i++) {
                var table = tables[i];
                html += '<tr class="adm-list-table-row">' +
                    '<td class="adm-list-table-cell">' + BX.util.htmlspecialchars(table.ID) + '</td>' +
                    '<td class="adm-list-table-cell">' + BX.util.htmlspecialchars(table.TITLE) + '</td>' +
                    '<td class="adm-list-table-cell">' + BX.util.htmlspecialchars(table.OWNER_TYPE + ':' + table.OWNER_ID) + '</td>' +
                    '<td class="adm-list-table-cell">' +
                    '<a href="javascript:void(0)" onclick="GrebionTableSelector.selectTable(' + table.ID + ', \'' + 
                    BX.util.htmlspecialchars(table.TITLE).replace(/'/g, '\\\'') + '\', \'' + inputId + '\', this)" class="adm-btn">Выбрать</a>' +
                    '</td>' +
                    '</tr>';
            }
            
            html += '</tbody></table>';
        }
        
        html += '</div>';
        
        BX('grebion-table-list').innerHTML = html;
    },
    
    /**
     * Выбрать таблицу
     * @param {number} tableId ID таблицы
     * @param {string} tableTitle Название таблицы
     * @param {string} inputId ID поля ввода
     * @param {Element} button Кнопка
     */
    selectTable: function(tableId, tableTitle, inputId, button) {
        var input = BX(inputId);
        var titleInput = input.parentNode.querySelector('.grebion-table-title');
        
        if (input) {
            input.value = tableId;
        }
        
        if (titleInput) {
            titleInput.value = tableTitle;
        }
        
        // Закрываем диалог
        var popup = BX.PopupWindow.getCurrentPopup();
        if (popup) {
            popup.close();
        }
    }
};

// Подключаем стили
if (!document.querySelector('#grebion-table-selector-styles')) {
    var style = document.createElement('style');
    style.id = 'grebion-table-selector-styles';
    style.textContent = `
        .grebion-table-selector {
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .grebion-table-title {
            min-width: 200px;
            background-color: #f5f5f5;
        }
        
        .grebion-table-selector-list {
            max-height: 300px;
            overflow-y: auto;
        }
    `;
    document.head.appendChild(style);
}