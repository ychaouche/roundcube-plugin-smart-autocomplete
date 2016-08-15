/**
 * Roundcube Smart Autocomplete
 *
 * @author Bostjan Skufca
 * @author Teon d.o.o.
 * @licence GNU AGPL
 * @copyright (c) 2016 Teon d.o.o.
 *
 **/

CREATE TABLE IF NOT EXISTS `smart_autocomplete` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `search_string` varchar(255) NOT NULL,
  `accepted_type` varchar(255) NOT NULL,
  `accepted_source` varchar(255) NOT NULL,
  `accepted_id` int(11) NOT NULL,
  `accepted_email` varchar(255) DEFAULT NULL,
  `accepted_count` int(11) NOT NULL,
  `accepted_datetime_first` datetime NOT NULL,
  `accepted_datetime_last` datetime NOT NULL,
  PRIMARY KEY (`id`),
  INDEX `user_id` (`user_id`),
  INDEX `search_string` (`search_string`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

REPLACE INTO system (name, value) VALUES ('smart-autocomplete-database-version', '2016041500');
