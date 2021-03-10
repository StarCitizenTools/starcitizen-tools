CREATE TABLE /*_*/babel (
	-- user id
	babel_user int UNSIGNED not null,
	-- language code
	babel_lang varchar(10) not null,
	-- level (1-5, N)
	babel_level VARCHAR(2) NOT NULL,

	PRIMARY KEY ( babel_user, babel_lang )
) /*$wgDBTableOptions*/;

-- Query all users who know a language at a specific level
CREATE INDEX /*i*/babel_lang_level ON /*_*/babel (babel_lang, babel_level);
