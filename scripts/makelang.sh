cd ../lang

# collect translatable strings
wp i18n make-pot .. Crypto-Payments-Woo.pot --domain=WCP_I18N_DOMAIN

# copy template to desired language and edit the translation file es_ES.po
cp Crypto-Payments-Woo.pot es_ES.po

# compile .po to .mo
msgfmt -o es_ES.mo es_ES.po
