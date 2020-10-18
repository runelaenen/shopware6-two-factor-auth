#!/usr/bin/env bash
echo "Fixing ecs errors"
php ../../../dev-ops/analyze/vendor/bin/ecs check --fix --config=../../../vendor/shopware/platform/easy-coding-standard.yml . --fix
