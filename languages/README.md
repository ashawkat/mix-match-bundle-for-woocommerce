# Mix & Match Bundle - Translation Files

## Available Languages

This plugin includes translation files for **28 languages** covering European, American, and Asian regions.

### European Languages (14)
- 🇫🇷 **French** (`fr_FR`)
- 🇩🇪 **German** (`de_DE`)
- 🇪🇸 **Spanish (Spain)** (`es_ES`)
- 🇮🇹 **Italian** (`it_IT`)
- 🇵🇹 **Portuguese (Portugal)** (`pt_PT`)
- 🇳🇱 **Dutch** (`nl_NL`)
- 🇵🇱 **Polish** (`pl_PL`)
- 🇸🇪 **Swedish** (`sv_SE`)
- 🇩🇰 **Danish** (`da_DK`)
- 🇳🇴 **Norwegian** (`nb_NO`)
- 🇫🇮 **Finnish** (`fi`)
- 🇬🇷 **Greek** (`el`)
- 🇷🇴 **Romanian** (`ro_RO`)
- 🇹🇷 **Turkish** (`tr_TR`)

### American Languages (3)
- 🇲🇽 **Spanish (Mexico)** (`es_MX`)
- 🇧🇷 **Portuguese (Brazil)** (`pt_BR`)
- 🇬🇧 **English (UK)** (`en_GB`)

### Asian Languages (11)
- 🇨🇳 **Chinese (Simplified)** (`zh_CN`)
- 🇹🇼 **Chinese (Traditional)** (`zh_TW`)
- 🇯🇵 **Japanese** (`ja`)
- 🇰🇷 **Korean** (`ko_KR`)
- 🇮🇳 **Hindi** (`hi_IN`)
- 🇸🇦 **Arabic** (`ar`)
- 🇹🇭 **Thai** (`th`)
- 🇻🇳 **Vietnamese** (`vi`)
- 🇮🇩 **Indonesian** (`id_ID`)
- 🇲🇾 **Malay** (`ms_MY`)
- 🇵🇭 **Filipino** (`fil`)

## Translation Statistics

- **Total Strings**: 218 translatable strings
- **File Format**: GNU gettext (.po/.pot)
- **Text Domain**: `mix-match-bundle`
- **Domain Path**: `/languages`
- **Version**: 1.0.2

## How to Translate

### Method 1: Using Poedit (Recommended)

1. **Download Poedit**: https://poedit.net/
2. **Open a PO file**: `mix-match-bundle-{locale}.po`
3. **Translate each string** in the msgstr field
4. **Save the file** - Poedit will automatically generate the .mo file
5. **Upload both files** (.po and .mo) to your WordPress site's plugin languages folder

### Method 2: Using Loco Translate Plugin

1. **Install** Loco Translate plugin from WordPress
2. **Go to** Loco Translate → Plugins → Mix & Match Bundle
3. **Select your language** or create a new translation
4. **Translate strings** in the web interface
5. **Save** - The plugin handles .mo file generation

### Method 3: Using WordPress.org Translation Platform

1. **Visit**: https://translate.wordpress.org/
2. **Navigate** to your language team
3. **Search** for "Mix & Match Bundle"
4. **Contribute** translations online
5. Translations will be automatically available in WordPress

### Method 4: Manual Translation

1. **Open** `mix-match-bundle-{locale}.po` in any text editor
2. **Find** each `msgid` line (source English text)
3. **Add translation** in the `msgstr ""` line below it
4. **Generate .mo file** using msgfmt:
   ```bash
   msgfmt mix-match-bundle-{locale}.po -o mix-match-bundle-{locale}.mo
   ```
5. **Upload both files** to your site

## File Structure

```
languages/
├── mix-match-bundle.pot          # Template file (source)
├── mix-match-bundle-fr_FR.po     # French translations
├── mix-match-bundle-fr_FR.mo     # Compiled French (generated)
├── mix-match-bundle-de_DE.po     # German translations
├── mix-match-bundle-de_DE.mo     # Compiled German (generated)
└── ... (other languages)
```

## Translation Context

### Key Areas to Translate

1. **Admin Interface**
   - Bundle editor labels and descriptions
   - Form fields and buttons
   - Settings and options
   - Success/error messages

2. **Frontend Display**
   - Product selection interface
   - Bundle summary
   - Cart messages
   - Discount information

3. **User Messages**
   - Success notifications
   - Error alerts
   - Validation messages
   - Help text

## Important Notes

### Placeholders
Some strings contain placeholders that should NOT be translated:
- `%s` - String placeholder
- `%%` - Literal percent sign
- HTML tags like `<strong>`, `<a>`, etc.

Example:
```
msgid "Bundle Discount (%s%% off)"
msgstr "Remise groupée (%s%% de réduction)"  # French
```

### Context Comments
Some strings have translator comments marked with `#:` or `#,` providing additional context about where and how the string is used.

### Pluralization
For languages with complex plural rules, use the appropriate `nplurals` and `plural` formula in the header.

## Testing Your Translation

1. **Upload files** to: `wp-content/languages/plugins/`
2. **Set WordPress language**: Settings → General → Site Language
3. **Clear cache** if using a caching plugin
4. **Test the plugin** to verify translations appear correctly

## Contributing Translations

If you've translated the plugin to your language and would like to contribute:

1. **Test thoroughly** to ensure accuracy
2. **Check for completeness** (all strings translated)
3. **Submit** via WordPress.org translation platform
4. **Or contact**: support@betatech.co

## Professional Translation Services

Need professional translation? Consider:
- **WPML** - https://wpml.org/
- **Weglot** - https://weglot.com/
- **TranslatePress** - https://translatepress.com/
- **Polylang** - https://polylang.pro/

## Support

For translation issues or questions:
- **Email**: support@betatech.co
- **Website**: https://betatech.co
- **Documentation**: https://betatech.co/docs/mix-match-bundle

---

**Thank you for helping make Mix & Match Bundle accessible to users worldwide!** 🌍

