.PHONY: help all clean test

CORE=http://www.unicode.org/Public/cldr/29/core.zip

help:
	@echo "'make all' to download CLDR data and rebuild files."
	@echo "'make test' to run the phpunit tests"
	@echo "'make clean' to delete the generated LanguageNames*.php files."
	@echo "'make distclean' to delete the CLDR data."

all: LanguageNames.php

distclean:
	rm -f core.zip
	rm -rf core

clean:
	rm -f CldrNames/CldrNames[A-Z]*.php

test:
	php ${MW_INSTALL_PATH}/tests/phpunit/phpunit.php tests

LanguageNames.php: core/
	php rebuild.php

core/: core.zip
	unzip core.zip -d core

core.zip:
	curl -C - -O $(CORE) || wget $(CORE) || fetch $(CORE)
