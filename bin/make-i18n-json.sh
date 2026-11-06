#!/usr/bin/env bash

GREEN_BOLD='\033[1;32m';
COLOR_RESET='\033[0m';

# Point JS source references at the built files.
for T in `find i18n -name "*.pot"`
	do
		sed \
			-e 's/#: src\/front[^:]*:/#: assets\/js\/front.js:/gp' \
			-e 's/#: src\/admin[^:]*:/#: assets\/js\/admin.js:/gp' \
			-e 's/#: src\/blocks[^:]*:/#: assets\/js\/blocks.js:/gp' \
			$T | uniq > $T-build
		rm $T
		mv $T-build $T
	done

printf "\n%bReplaced development mappings with production files in devdiggers-returns-for-woocommerce.pot%b\n\n" "$GREEN_BOLD" "$COLOR_RESET"
