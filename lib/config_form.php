<?php
/**
 * Konfiguration - Basisdaten und Defaults
 *
 * @package geolocation
 */

namespace Geolocation;

class config_form extends \rex_config_form
{

    /**
     *   Work-around für REDAXO ab 5.12 betreffend validator->notEmpty
     *
     *   seit 5.12 wird notEmpty automatisch zu einem "required"-Input mit Client-Validierung
     *   Das mag ich nicht; daher das Required-Attribut wieder per JS abschalten (geht nicht anders)
     *   Die Eingabe wieder per Validator klassisch prüfen.
     *
     *   @param rex_form_element     Das zu modifizierende Formular-Element
     */
    public function ensureOldNotEmptyBehavoir( \rex_form_element $field )
    {
        static $R_5_12 = null;

        // beim ersten Aufruf initialisieren
        if( null === $R_5_12 ) {
            $R_5_12 = \rex_version::compare(\rex::getVersion(),'5.11.99','>');
            if( $R_5_12 ) {
                $id = 'rf' . md5(microtime());
                $this->setFormId( $id );
                $this->addRawField('
                <script>
                document.addEventListener("DOMContentLoaded", function(){
                    let item, form = document.getElementById(\''.$id.'\');
                    if( !form ) return;
                    form.querySelectorAll( \'form input[remove_required="1"]\').forEach( (item) => item.removeAttribute("required") );
                });
                </script>
                ');
            };
        }
        // Feld anpassen
        if( $R_5_12 ) {
            $field->setAttribute('remove_required',1);
        }
    }

    /**
     *   Initialisiert das Formular selbst
     *
     */
    public function init(){
        parent::init();

        if( !PROXY_ONLY ){
            $this->addFieldset( \rex_i18n::msg('geolocation_config_map') );

                $field = $this->addSelectField('default_map',$value = null,['class'=>'form-control']);
                $field->setLabel( \rex_i18n::msg('geolocation_config_map_default') );
                $select = $field->getSelect();
                $select->addSqlOptions( 'SELECT concat(title," [id=",id,"]") as name,id FROM '.mapset::table()->getTableName().' ORDER BY title' );

                $field = $this->addTextField( 'map_bounds' );
                $field->setLabel( \rex_i18n::msg('geolocation_form_map_bounds') );
                $field->setNotice( \rex_i18n::msg('geolocation_form_map_bounds_notice') );
                $errorMsg = \rex_i18n::msg('geolocation_form_map_bounds_error');
                $field->getValidator()
                      ->add( 'notEmpty', $errorMsg )
                      ->add( 'match', $errorMsg.'#', '/^\s*\[[+-]?\d+(\.\d+)?,\s*[+-]?\d+(\.\d+)?\],\s*[[+-]?\d+(\.\d+)?,\s*[+-]?\d+(\.\d+)?\]\s*$/');
                $this->ensureOldNotEmptyBehavoir( $field );

                $field = $this->addTextField( 'map_zoom' );
                $field->setLabel( \rex_i18n::msg('geolocation_form_map_zoom') );
                $field->setNotice( \rex_i18n::msg('geolocation_form_map_zoom_notice',ZOOM_MIN,ZOOM_MAX) );
                $errorMsg = \rex_i18n::msg('geolocation_form_map_zoom_error');
                $field->getValidator()
                      ->add( 'notEmpty', $errorMsg)
                      ->add( 'type', $errorMsg, 'integer')
                      ->add( 'min', $errorMsg, ZOOM_MIN)
                      ->add( 'max', $errorMsg,ZOOM_MAX);
                $this->ensureOldNotEmptyBehavoir( $field );

                $field = $this->addCheckboxField('map_components');
                $field->setLabel(\rex_i18n::msg('geolocation_form_mapoptions'));
                foreach( mapset::$mapoptions as $k=>$v ) {
                    $field->addOption(\rex_i18n::translate($v), $k);
                }

                $field = $this->addTextField( 'map_outfragment' );
                $field->setLabel( \rex_i18n::msg('geolocation_form_outfragment') );
                $field->setNotice( \rex_i18n::msg('geolocation_form_map_outfragment_notice',OUT) );
                $errorMsg = \rex_i18n::msg('geolocation_form_map_outfragment_error');
                $field->getValidator()
                      ->add( 'notEmpty', $errorMsg)
                      ->add( 'match', $errorMsg, '/^.*?\.php$/');
                $this->ensureOldNotEmptyBehavoir( $field );
        }

        $this->addFieldset( \rex_i18n::msg('geolocation_config_proxycache') );

            $field = $this->addTextField( 'cache_ttl' );
            $field->setLabel( \rex_i18n::msg('geolocation_form_proxycache_ttl') );
            $field->setNotice( \rex_i18n::rawMsg('geolocation_form_proxycache_ttl_notice',TTL_MAX) );
            $errorMsg = \rex_i18n::msg('geolocation_form_proxycache_ttl_error',TTL_MAX);
            $field->getValidator()
                  ->add( 'notEmpty', $errorMsg )
                  ->add( 'type', $errorMsg, 'integer' )
                  ->add( 'min', $errorMsg, TTL_MIN )
                  ->add( 'max', $errorMsg, TTL_MAX );
            $this->ensureOldNotEmptyBehavoir( $field );

            $field = $this->addTextField( 'cache_maxfiles' );
            $field->setLabel( \rex_i18n::msg('geolocation_form_proxycache_maxfiles') );
            $field->setNotice( \rex_i18n::msg('geolocation_form_proxycache_maxfiles_notice') );
            $errorMsg = \rex_i18n::msg('geolocation_form_proxycache_maxfiles_error',CFM_MIN);
            $field->getValidator()
                  ->add( 'notEmpty', $errorMsg )
                  ->add( 'type', $errorMsg, 'integer' )
                  ->add( 'min', $errorMsg, CFM_MIN );
            $this->ensureOldNotEmptyBehavoir( $field );

    }

    /**
     *   Feldwerte abrufen - Sonderfall 'outfragment'
     *
     *   Das Feld muss immer gefüllt wein, daher wird hier wenn es leer ist der Fallback-Wert
     *   eingetragen.
     *
     *   @param bool     true für "erfolgreich gespeichert", sonst false
     */
    protected function getValue($name)
    {
        $value = parent::getValue($name);
        if( !$value && 'outfragment' === $name ) {
            $value = OUT;
        }
        return $value;
    }

    /**
     *   Speichert die Änderungen in der Datenbank
     *
     *   Das formal kritische Freitextfeld map_bounds wird normalisiert
     *   Auf Basis der Setttings werden die Assets neu kompiliert.
     *
     *   @param bool     true für "erfolgreich gespeichert", sonst false
     */
    protected function save() : bool
    {
        // Bounds-Koordinaten um Leerzeichen erleichtern (nur wenn Karten-Fieldset aktiviert)
        $bounds = $this->getElement(\rex_i18n::msg('geolocation_config_map'),'map_bounds');
        if( $bounds ){
            $value = $bounds->getValue();
            $value = str_replace(' ','',$value);
            $bounds->setValue($value);
        }

        $ok = parent::save();

        // Die Änderungen auch nach /assets/addons/geolocation/ schreiben
        if( $ok ) {
            self::compileAssets( );
        }

        return $ok;
    }

    /**
     *   Compiliert die Assets (CSS/JS) basierend auf den Settings neu
     *
     *   Siehe Dokumentation
     *
     *   @param ?string      null= reguläres Addon-Verzeichnis oder eben das angegebene Verzeichnis
     */
    static function compileAssets( ?string $addonDir=null ) : void
    {

        $addonDir = $addonDir ?: \rex_path::addon(ADDON);
        $dataDir = \rex_path::addonData(ADDON);
        $assetDir = \rex_path::addonAssets(ADDON);

        // AssetPacker-Instanzen für die Asset-Dateien öffnen
        // Kartensoftware
        $css = AssetPacker\AssetPacker::target( $assetDir.'geolocation.min.css')
            ->overwrite();
        $js = AssetPacker\AssetPacker::target( $assetDir.'geolocation.min.js')
            ->overwrite();
        // CCS für Backend-Formulare
        $be_css = AssetPacker\AssetPacker::target( $assetDir.'geolocation_be.min.css')
            ->overwrite()
            ->addFile( $addonDir.'install/geolocation_be.css' );

        // Leaflet und Co wird nur eingebaut, wenn auch angefordert
        if( 0 < \rex_config::get(ADDON,'compile',2) ){
            // der Leaflet-Core
            $css
                ->addFile( $addonDir.'install/vendor/leaflet/leaflet.css' );
            $js
                ->addFile( $addonDir.'install/vendor/leaflet/leaflet.js',true )
                ->replace( '//# sourceMappingURL=leaflet.js.map','' );
        }
        if( 1 < \rex_config::get(ADDON,'compile',2) ){
            // Zusätzlich die von Geolocation benötigten Plugins und der Geolocation-Code
            $css
                ->addFile( $addonDir.'install/vendor/Leaflet.GestureHandling/leaflet-gesture-handling.min.css' )
                ->addFile( $addonDir.'install/geolocation.css' );
            $js
                ->addFile( $addonDir.'install/vendor/Leaflet.GestureHandling/leaflet-gesture-handling.min.js' )
                ->replace( '//# sourceMappingURL=leaflet-gesture-handling.min.js.map','' )
                ->addFile( $addonDir.'install/geolocation.js' )
                ->replace( '%defaultBounds%', \rex_config::get(ADDON,'map_bounds') )
                ->replace( '%defaultZoom%', \rex_config::get(ADDON,'map_zoom') )
                ->replace( '%zoomMin%', \rex_config::get(ADDON,'map_zoom_min') )
                ->replace( '%zoomMax%', \rex_config::get(ADDON,'map_zoom_max') )
                ->replace( '%defaultFullscreen%', (false === strpos(\rex_config::get(ADDON,'map_components'),'|fullscreen|') ? 'false' : 'true') )
                ->replace( '%defaultGestureHandling%', (false === strpos(\rex_config::get(ADDON,'map_components'),'|gestureHandling|') ? 'false' : 'true') )
                ->replace( '%defaultLocateControl%', (false === strpos(\rex_config::get(ADDON,'map_components'),'|locateControl|') ? 'false' : 'true') )
                ->replace( '%i18n%', \rex_file::get($dataDir.'lang_js',\rex_file::get($addonDir.'install/lang_js','')) );
        }

        // Die optionalen individuellen Komponenten aus dem data-Verzeichnis holen
        // ggf. zusätzliche komplexe Elemente dazuladen
        if( is_readable($dataDir.'load_assets.php') ) {
            include $dataDir.'load_assets.php';
        } else {
            $css
                ->addOptionalFile( $dataDir.'geolocation.css');
            $js
                ->addOptionalFile( $dataDir.'geolocation.js')
                ->regReplace( '%//#\s+sourceMappingURL=.*?$%im','//' );
            $be_css
                ->addOptionalFile( $dataDir.'geolocation_be.css');

        }
        // Zieldateien final erstellen
        $js->create();
        $css->create();
        $be_css->create();

    }

}
