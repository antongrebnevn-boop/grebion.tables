-- Удаление таблиц модуля Grebion.Tables NextGen

-- Удаляем таблицы в обратном порядке из-за внешних ключей
DROP TABLE IF EXISTS `grebion_table_cells`;
DROP TABLE IF EXISTS `grebion_table_rows`;
DROP TABLE IF EXISTS `grebion_table_columns`;
DROP TABLE IF EXISTS `grebion_tables`;
DROP TABLE IF EXISTS `grebion_table_schemas`;