CREATE TABLE flynsarmysociallogin_customer_providers (
	id int(11) NOT NULL AUTO_INCREMENT,
	shop_customer_id int(11) NOT NULL,
	provider_id varchar(255) DEFAULT NULL,
	provider_token varchar(255) DEFAULT NULL,
	is_enabled tinyint(4) DEFAULT NULL,
	PRIMARY KEY (id),
	KEY shop_customer_id (shop_customer_id),
	KEY provider_id (provider_id),
	KEY is_enabled (is_enabled)
);