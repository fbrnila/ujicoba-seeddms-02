#!/bin/bash

export XDEBUG_MODE=coverage
export SEEDDMS_CORE_SQL=../../seeddms/install/create_tables-sqlite3.sql
majorversion=$(git rev-parse --abbrev-ref HEAD | sed -e "s/seeddms-\([56]\).*/\1/")
minorversion=$(git rev-parse --abbrev-ref HEAD | sed -e "s/seeddms-\([56]\)\.\([0-9]\).*/\2/")
srcdir=.

vendor/bin/phpunit --bootstrap ${srcdir}/bootstrap-${majorversion}.${minorversion}.php --coverage-html ${srcdir}/coverage/ --display-deprecations --display-warnings
#--filter DocumentTest::testGetInstance
