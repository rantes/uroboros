import { endpoints, CURRENT_LOCALE } from '../../libs/app/configs.js';
import { BaseModelClass } from './base-model.factory.js';

const TRANSLATIONS = {};
const BaseModel = new BaseModelClass();
TRANSLATIONS[CURRENT_LOCALE] = {};
/**
 *
 * @param key
 * @returns {Promise<string>}
 * @private
 */
export function _(key) {
    return TRANSLATIONS[CURRENT_LOCALE][key[0]];
}

/**
 *
 * @param locale
 * @returns {Promise<void>}
 */
async function fillTranslations(locale) {
    BaseModel.url(`${endpoints.translations.translation}?locale=${locale}`);
    const data = await BaseModel.getElement(`translations-${locale}`).then((data) => data);
    data.forEach((element) => {
        TRANSLATIONS[CURRENT_LOCALE][element.keyid] = element.translation;
    });
}

if (Object.keys(TRANSLATIONS[CURRENT_LOCALE]).length === 0) {
    fillTranslations(CURRENT_LOCALE);
}


