-- Создание таблиц для модуля Grebion.Tables NextGen

-- Таблица для хранения схем таблиц
CREATE TABLE IF NOT EXISTS `grebion_table_schemas` (
    `ID` int(11) NOT NULL AUTO_INCREMENT,
    `NAME` varchar(255) NOT NULL COMMENT 'Название схемы',
    `DESCRIPTION` text COMMENT 'Описание схемы',
    `SCHEMA` longtext NOT NULL COMMENT 'JSON-схема колонок',
    `CREATED_AT` datetime NOT NULL COMMENT 'Дата создания',
    `UPDATED_AT` datetime NOT NULL COMMENT 'Дата обновления',
    PRIMARY KEY (`ID`),
    UNIQUE KEY `UX_GREBION_SCHEMAS_NAME` (`NAME`),
    KEY `IX_GREBION_SCHEMAS_CREATED` (`CREATED_AT`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Схемы таблиц';

-- Основная таблица для хранения данных таблиц
CREATE TABLE IF NOT EXISTS `grebion_tables` (
    `ID` int(11) NOT NULL AUTO_INCREMENT,
    `SCHEMA_ID` int(11) NOT NULL COMMENT 'ID схемы таблицы',
    `OWNER_TYPE` varchar(50) NOT NULL COMMENT 'Тип владельца (USER, IBLOCK_ELEMENT, etc.)',
    `OWNER_ID` int(11) NOT NULL COMMENT 'ID владельца',
    `TITLE` varchar(255) DEFAULT NULL COMMENT 'Название таблицы',
    `CREATED_AT` datetime NOT NULL COMMENT 'Дата создания',
    `UPDATED_AT` datetime DEFAULT NULL COMMENT 'Дата обновления',
    PRIMARY KEY (`ID`),
    KEY `IX_GREBION_TABLES_SCHEMA` (`SCHEMA_ID`),
    KEY `IX_GREBION_TABLES_OWNER` (`OWNER_TYPE`, `OWNER_ID`),
    KEY `IX_GREBION_TABLES_CREATED` (`CREATED_AT`),
    CONSTRAINT `FK_GREBION_TABLES_SCHEMA` FOREIGN KEY (`SCHEMA_ID`) REFERENCES `grebion_table_schemas` (`ID`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Таблицы модуля Grebion.Tables';

-- Таблица для хранения колонок
CREATE TABLE IF NOT EXISTS `grebion_table_columns` (
    `ID` int(11) NOT NULL AUTO_INCREMENT,
    `TABLE_ID` int(11) NOT NULL COMMENT 'ID таблицы',
    `CODE` varchar(50) NOT NULL COMMENT 'Символьный код колонки',
    `TYPE` varchar(20) NOT NULL DEFAULT 'string' COMMENT 'Тип колонки',
    `TITLE` varchar(255) NOT NULL COMMENT 'Название колонки',
    `SORT` int(11) NOT NULL DEFAULT 500 COMMENT 'Порядок сортировки',
    `SETTINGS` longtext COMMENT 'JSON-настройки колонки',
    `CREATED_AT` datetime NOT NULL COMMENT 'Дата создания',
    `UPDATED_AT` datetime DEFAULT NULL COMMENT 'Дата обновления',
    PRIMARY KEY (`ID`),
    UNIQUE KEY `UX_GREBION_COLUMNS_TABLE_CODE` (`TABLE_ID`, `CODE`),
    KEY `IX_GREBION_COLUMNS_TABLE` (`TABLE_ID`),
    KEY `IX_GREBION_COLUMNS_SORT` (`TABLE_ID`, `SORT`),
    CONSTRAINT `FK_GREBION_COLUMNS_TABLE` FOREIGN KEY (`TABLE_ID`) REFERENCES `grebion_tables` (`ID`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Колонки таблиц';

-- Таблица для хранения строк данных
CREATE TABLE IF NOT EXISTS `grebion_table_rows` (
    `ID` int(11) NOT NULL AUTO_INCREMENT,
    `TABLE_ID` int(11) NOT NULL COMMENT 'ID таблицы',
    `SORT` int(11) NOT NULL DEFAULT 500 COMMENT 'Порядок сортировки',
    `DATA` longtext COMMENT 'JSON-данные строки',
    `CREATED_AT` datetime NOT NULL COMMENT 'Дата создания',
    `UPDATED_AT` datetime DEFAULT NULL COMMENT 'Дата обновления',
    PRIMARY KEY (`ID`),
    KEY `IX_GREBION_ROWS_TABLE` (`TABLE_ID`),
    KEY `IX_GREBION_ROWS_SORT` (`TABLE_ID`, `SORT`),
    CONSTRAINT `FK_GREBION_ROWS_TABLE` FOREIGN KEY (`TABLE_ID`) REFERENCES `grebion_tables` (`ID`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Строки данных таблиц';

-- Таблица для хранения ячеек (опциональная, для детального управления)
CREATE TABLE IF NOT EXISTS `grebion_table_cells` (
    `ID` int(11) NOT NULL AUTO_INCREMENT,
    `ROW_ID` int(11) NOT NULL COMMENT 'ID строки',
    `COLUMN_ID` int(11) NOT NULL COMMENT 'ID колонки',
    `VALUE` longtext COMMENT 'Значение ячейки',
    `FORMATTED_VALUE` longtext COMMENT 'Отформатированное значение',
    `CREATED_AT` datetime NOT NULL COMMENT 'Дата создания',
    `UPDATED_AT` datetime DEFAULT NULL COMMENT 'Дата обновления',
    PRIMARY KEY (`ID`),
    UNIQUE KEY `UX_GREBION_CELLS_ROW_COLUMN` (`ROW_ID`, `COLUMN_ID`),
    KEY `IX_GREBION_CELLS_ROW` (`ROW_ID`),
    KEY `IX_GREBION_CELLS_COLUMN` (`COLUMN_ID`),
    CONSTRAINT `FK_GREBION_CELLS_ROW` FOREIGN KEY (`ROW_ID`) REFERENCES `grebion_table_rows` (`ID`) ON DELETE CASCADE,
    CONSTRAINT `FK_GREBION_CELLS_COLUMN` FOREIGN KEY (`COLUMN_ID`) REFERENCES `grebion_table_columns` (`ID`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Ячейки таблиц (детальное управление)';

-- Индексы для оптимизации поиска
CREATE INDEX `IX_GREBION_TABLES_TITLE` ON `grebion_tables` (`TITLE`);
CREATE INDEX `IX_GREBION_COLUMNS_TYPE` ON `grebion_table_columns` (`TYPE`);
CREATE INDEX `IX_GREBION_ROWS_CREATED` ON `grebion_table_rows` (`CREATED_AT`);
CREATE INDEX `IX_GREBION_CELLS_VALUE` ON `grebion_table_cells` (`VALUE`(255));