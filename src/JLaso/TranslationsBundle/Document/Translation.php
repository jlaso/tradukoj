<?php
namespace JLaso\TranslationsBundle\Document;

use Doctrine\ODM\MongoDB\Mapping\Annotations as MongoDB;
use Doctrine\Bundle\MongoDBBundle\Validator\Constraints\Unique as MongoDBUnique;
use Doctrine\ORM\Mapping\HasLifecycleCallbacks;
use Onbile\CoreBundle\Validator\Constraints as OnbileAssert;
use Doctrine\Common\Collections\ArrayCollection;
use Onbile\CoreBundle\Document\Menu;
use Onbile\CoreBundle\Document\Page;
use Onbile\CoreBundle\Document\WebTheme;
use Onbile\CoreBundle\Document\Locale;
use Onbile\CoreBundle\Document\DataSet;
use Onbile\CoreBundle\Tests\Unit\Services\Locator\WebResourcesPathLocatorTest;
use Symfony\Component\Validator\Constraints as Assert;


use JMS\Serializer\Annotation as Serializer;
use JMS\Serializer\Annotation\Exclude;


/**
 * Translation
 *
 * @MongoDBUnique(fields="longkey")
 * @MongoDB\Document(repositoryClass="JLaso\TranslationsBundle\Document\Repository\TranslationRepository")
 */
class Translation
{
    /**
     * @MongoDB\Id
     * @Serializer\Type("string")
     */
    protected $id;

    /**
     *
     * @MongoDB\Int
     * @Serializer\Type("integer")
     */
    protected $projectId;

    /**
     *
     * @MongoDB\Int
     * @Serializer\Type("integer")
     */
    protected $webUserId;

    /**
     *
     * @MongoDB\Boolean
     * @Serializer\Type("boolean")
     */
    protected $isDemo;

    /**
     * @MongoDB\String
     * @Serializer\Type("string")
     */
    protected $demoId;

    /**
     *
     * @MongoDB\ReferenceOne(targetDocument="Demo")
     * @Exclude
     */
    protected $demo;

    /**
     * Indica si esta web es visible públicamente o no.
     * Se utiliza
     *     - para crear demos en pruebas que todavía no se han probado suficientemente y por eso no son públicas.
     *     - para que el usuario despublique su web.
     *
     * @MongoDB\Boolean
     * @Serializer\Type("boolean")
     */
    protected $published;

    /**
     * Indica si esta web esta al corriente de sus pagos y es visible
     * Se utiliza
     *     - controlar el pago/impago de los servicios.
     *
     * @MongoDB\Boolean
     * @Serializer\Type("boolean")
     */
    protected $enabled;

    /**
     * Indica si una web ha sido eliminada por el cliente
     *
     * @MongoDB\Boolean
     * @Serializer\Type("boolean")
     */
    protected $deleted;

    /**
     * Enlace con la pasarela que enlaza las webs con los temas
     *
     * @MongoDB\ReferenceOne(targetDocument="WebTheme")
     * @Exclude
     */
    protected $webTheme;

    /**
     * Idioma en el que se ha creado el sitio web.
     *
     * @MongoDB\ReferenceOne(targetDocument="Theme")
     * @Exclude
     */
    protected $theme;

    /**
     * Idioma en el que se ha creado el sitio web.
     *
     * @MongoDB\ReferenceOne(targetDocument="Locale")
     * @Exclude
     */
    protected $locale;

    /**
     * Juego de datos activo del sitio web. Coincide con el
     * tipo de negocio del creador de la web.
     *
     * @MongoDB\ReferenceOne(targetDocument="DataSet")
     * @Exclude
     */
    protected $dataSet;

    /**
     * Array con los valores de las keys y sus traducciones.
     *
     * @MongoDB\Hash
     * @Serializer\Type("array")
     */
    protected $translations;

    /**
     * Define el menú principal de navegación del sitio web.
     *
     * @MongoDB\EmbedOne(targetDocument="Menu")
     * @Exclude
     */
    private $menu;

    /**
     * Colección de páginas que forman el sitio web.
     *
     *
     *
     * @MongoDB\ReferenceMany(targetDocument="Page", cascade="all")
     * @Exclude
     */
    protected $pages;

    /**
     * Indica la página que se utiliza como portada del sitio web.
     *
     * @MongoDB\ReferenceOne(targetDocument="Page")
     * @Exclude
     */
    protected $homepage;

    /**
     * Subdominio de 'zeendo.com' a través del que se accede a cada sitio
     * web. Este subdominio es obligatorio y es lo que se utiliza por defecto
     * a menos que el usuario configure su propio dominio completo, que se
     * guarda en la propiedad $domain.
     *
     * @MongoDB\String
     * @OnbileAssert\Subdomain
     * @Serializer\Type("string")
     */
    protected $subdomain;

    /**
     * Indica si el subdominio ha sido cambiado
     *
     * @MongoDB\Boolean
     * @Serializer\Type("boolean")
     */
    protected $defaultSubdomain;

    /**
     * Dominio completo del sitio web, incluyendo las 'www' si el cliente
     * las escribe al crear la web.
     *
     * @MongoDB\String
     * @MongoDB\Index(unique=true)
     * @Serializer\Type("string")
     */
    protected $domain;

    /**
     * Valor de la etiqueta <title>
     *
     * @MongoDB\String
     * @Serializer\Type("string")
     */
    protected $metaTitle;

    /**
     * Valor de la etiqueta <meta name="description">
     *
     * @MongoDB\String
     * @Serializer\Type("string")
     */
    protected $metaDescription;

    /**
     * Valor de la etiqueta <meta name="keywords">
     *
     * @MongoDB\String
     * @Serializer\Type("string")
     */
    protected $metaKeywords;

    /**
     * Ruta del archivo favicon.ico del sitio web. Normalmente este
     * archivo estará alojado en un servicio externo de CDN.
     *
     * @MongoDB\String
     * @Serializer\Type("string")
     */
    protected $favicon;

    /**
     * Ruta de la imagen del logotipo del sitio web. Normalmente este
     * archivo estará alojado en un servicio externo de CDN. Si esta propiedad
     * tiene un valor, no se utiliza la propiedad $logoText
     *
     * @MongoDB\String
     * @Serializer\Type("string")
     */
    protected $logoImage;

    /**
     * Texto que se muestra como logotipo del sitio para aquellas web
     * que no tienen un logotipo gráfico. Esta propiedad sólo se
     *
     * @MongoDB\String
     * @Serializer\Type("string")
     */
    protected $logoText;

    /**
     * Tamaña del logo
     *
     * @MongoDB\Int
     * @Serializer\Type("integer")
     */
    protected $logoSize;

    /**
     * El código numérico que utiliza el sistema de estadísticas interno basado en
     * Piwik, ver http://es.piwik.org
     *
     * @MongoDB\Int
     * @Serializer\Type("integer")
     */
    protected $piwikId;

    /**
     * El código numérico de la cuenta de Google Analytics asociada
     * a esta web. Aquí *no* se incluye todo el código JavaScript
     * necesario para Google Analytics, solo el número de la cuenta
     * de usuario.
     *
     * @MongoDB\String
     * @Serializer\Type("string")
     */
    protected $googleAnalytics;

    /**
     * El código numérico que Google requiere para verificar que
     * el usuario es realmente el dueño de un sitio web.
     *
     * @MongoDB\String
     * @Serializer\Type("string")
     */
    protected $googleVerification;

    /**
     * URL del perfil de Facebook del creador del sitio web.
     *
     * @MongoDB\String
     * @Serializer\Type("string")
     */
    protected $socialFacebook;

    /**
     * URL del perfil de Twitter del creador del sitio web.
     *
     * @MongoDB\String
     * @Serializer\Type("string")
     */
    protected $socialTwitter;

    /**
     * URL del perfil de Google Plus del creador del sitio web.
     *
     * @MongoDB\String
     * @Serializer\Type("string")
     */
    protected $socialGooglePlus;

    /**
     * URL del perfil de Vimeo del creador del sitio web.
     *
     * @MongoDB\String
     * @Serializer\Type("string")
     */
    protected $socialVimeo;

    /**
     * URL del perfil de Tuenti del creador del sitio web.
     *
     * @MongoDB\String
     * @Serializer\Type("string")
     */
    protected $socialTuenti;

    /**
     * URL del perfil de Pinterest del creador del sitio web.
     *
     * @MongoDB\String
     * @Serializer\Type("string")
     */
    protected $socialPinterest;

    /**
     * Nombre del autor del sitio web. Puede ser el nombre de una
     * empresa (Hotel ACME) o el nombre de una persona en el caso
     * de los negocios pequeños (Fontanería Manolo).
     *
     * @MongoDB\String
     * @Serializer\Type("string")
     */
    protected $authorName;

    /**
     * Número de teléfono del autor del sitio web.
     *
     * @MongoDB\String
     * @Serializer\Type("string")
     */
    protected $authorTelephone;

    /**
     * Dirección que el autor del sitio web quiere mostrar públicamente.
     * Normalmente será la dirección de su tienda o negocio, pero podrían
     * ser también las coordenadas geográficas de un lugar específico.
     *
     * @MongoDB\String
     * @Serializer\Type("string")
     */
    protected $authorAddress;

    /**
     * Email del autor del sitio web.
     *
     * @MongoDB\String
     * @Serializer\Type("string")
     */
    protected $authorEmail;

    /**
     * @MongoDB\Timestamp
     * @Serializer\Type("string")
     */
    protected $createdAt;

    /**
     * @MongoDB\Timestamp
     * @Serializer\Type("string")
     */
    protected $updatedAt;

    
    /**
     * Almacena el html que se vera al hacer el preview en el editor
     *
     * @MongoDB\String
     * @Serializer\Type("string")
     */
    protected $htmlContentPreview;

    /**
     * Estilos propios de la page
     * @MongoDB\Hash
     * @Serializer\Type("array")
     */
    protected $customStyles;

    /**
     * @var string $realmName
     *
     * @MongoDB\String
     * @Serializer\Type("string")
     */
    protected $realmName;

    /**
     * Indica que se ha publicado y por tanto se tiene que actualizar su cache
     *
     * @MongoDB\Boolean
     * @Serializer\Type("boolean")
     */
    protected $invalidateCache;

    public function __construct()
    {
        $this->enabled = true;
        $this->deleted = false;
        $this->published = true;
        $this->userId = null;
        $this->isDemo = true;
        $this->pages = new ArrayCollection();
        $this->createdAt = new \MongoTimestamp();
        $this->updatedAt = new \MongoTimestamp();
        $this->defaultSubdomain = true;
        $this->siteSize = 900;
        $this->logoSize = 50;
        $this->headerTemplate = 'header.html.twig';
        $this->footerTemplate = 'footer.html.twig';
        $this->piwikId = 0;
        $this->invalidateCache = true;
        $this->publishedAt = new \MongoTimestamp();
    }

    public function getDateTimeDateOfPublication()
    {
        $date = new \DateTime();

        return $date->setTimestamp($this->publishedAt->__toString());
    }

    public function setNewUpdatedAt()
    {
        $this->setUpdatedAt(time());
    }

    public function getKeyColorSet()
    {
        $styles = $this->getStyles();

        return $styles['colorSets']['key'];
    }

    /**
     * TODO: si color1 no existe devolver el primer elemento del array, si tampoco exite devolver la key
     * @return bool
     */
    public function getPrimaryColorSet()
    {
        $styles = $this->getStyles();


        if (isset($styles['colorSets']['properties']['color1'])) {

            return $styles['colorSets']['properties']['color1'];
        }

        return $this->getKeyColorSet();
    }

    /**
     * @return Theme
     */
    public function getTheme()
    {
        return $this->theme;
//        return $this->getWebTheme()->getTheme();
    }

    public function setTheme($theme)
    {
        $this->theme = $theme;
    }

    public function reset()
    {
        $this->id = null;
        $this->isDemo = false;
        $this->createdAt = time();
        $this->updatedAt = time();
        $this->enabled = true;
        $this->published = true;

        $this->menu = new Menu();
    }

    /**
     * Add pages
     *
     * @param Page $page
     */
    public function addPage(Page $page)
    {
        $this->pages[] = $page;
        $page->setWeb($this);
    }

    public function setPages($pages)
    {
        $this->pages = $pages;
    }

    /**
     * Get id
     *
     * @return id $id
     */
    public function getId()
    {
        return $this->id;
    }

    public function setId($id)
    {
        $this->id = $id;

        return $this;
    }

    /**
     * Set isDemo
     *
     * @param  boolean $isDemo
     * @return Web
     */
    public function setIsDemo($isDemo)
    {
        $this->isDemo = $isDemo;

        return $this;
    }

    /**
     * Get isDemo
     *
     * @return boolean $isDemo
     */
    public function getIsDemo()
    {
        return $this->isDemo;
    }

    /**
     * Set enabled
     *
     * @param  boolean $enabled
     * @return Web
     */
    public function setEnabled($enabled)
    {
        $this->enabled = $enabled;

        return $this;
    }

    /**
     * Get enabled
     *
     * @return boolean $enabled
     */
    public function getEnabled()
    {
        return $this->enabled;
    }

    public function setPublished($published)
    {
        $this->published = $published;
    }

    public function getPublished()
    {
        return $this->published;
    }

    public function setDeleted($deleted)
    {
        $this->deleted = $deleted;
    }

    public function getDeleted()
    {
        return $this->deleted;
    }

    /**
     * Devuelve estado de activa para los casos de publicada, habilitada y no borrada
     *
     * @return bool
     */
    public function isActive()
    {
        return $this->getPublished() && $this->getEnabled() && !$this->getDeleted();
    }

    /**
     * Set webTheme
     *
     * @param WebTheme $webTheme
     * @return Web
     */
    public function setWebTheme(WebTheme $webTheme)
    {
        $this->webTheme = $webTheme;

        return $this;
    }

    /**
     * Get webTheme
     *
     * @return WebTheme $webTheme
     */
    public function getWebTheme()
    {
        return $this->webTheme;
    }

    /**
     * Set locale
     *
     * @param Locale $locale
     * @return Web
     */
    public function setLocale(Locale $locale)
    {
        $this->locale = $locale;

        return $this;
    }

    /**
     * Get locale
     *
     * @return Locale $locale
     */
    public function getLocale()
    {
        return $this->locale;
    }

    /**
     * Set dataSet
     *
     * @param DataSet $dataSet
     * @return Web
     */
    public function setDataSet(DataSet $dataSet)
    {
        $this->dataSet = $dataSet;

        return $this;
    }

    /**
     * Get dataSet
     *
     * @return DataSet $dataSet
     */
    public function getDataSet()
    {
        return $this->dataSet;
    }

    /**
     * Set menu
     *
     * @param Menu $menu
     * @return Web
     */
    public function setMenu(Menu $menu)
    {
        $this->menu = $menu;

        return $this;
    }

    /**
     * Get menu
     *
     * @return Menu $menu
     */
    public function getMenu()
    {
        return $this->menu;
    }

    public function getPages()
    {
        return $this->pages;
    }

    /**
     * Set homepage
     *
     * @param Page $homepage
     * @return Web
     */
    public function setHomepage(Page $homepage)
    {
        $this->homepage = $homepage;

        return $this;
    }

    /**
     * Get homepage
     *
     * @return Page $homepage
     */
    public function getHomepage()
    {
        return $this->homepage;
    }

    /**
     * Set domain
     *
     * @param  string $domain
     * @return Web
     */
    public function setDomain($domain)
    {
        $this->domain = $domain;

        return $this;
    }

    /**
     * Get domain
     *
     * @return string $domain
     */
    public function getDomain()
    {
        return $this->domain;
    }

    /**
     * Set subdomain
     *
     * @param  string $subdomain
     * @return Web
     */
    public function setSubdomain($subdomain)
    {
        $this->subdomain = $subdomain;

        return $this;
    }

    /**
     * Get subdomain
     *
     * @return string $subdomain
     */
    public function getSubdomain()
    {
        return $this->subdomain;
    }


    /**
     * Set defaultSubdomain
     *
     * @param  boolean $defaultSubdomain
     * @return Web
     */
    public function setDefaultSubdomain($defaultSubdomain)
    {
        $this->defaultSubdomain = $defaultSubdomain;

        return $this;
    }

    /**
     * Get defaultSubdomain
     *
     * @return boolean $defaultSubdomain
     */
    public function getDefaultSubdomain()
    {
        return $this->defaultSubdomain;
    }

    /**
     * Get domain if exists else subdomain
     *
     * @return string
     */
    public function getDomainOrSubDomain()
    {
        return null === $this->domain ? $this->subdomain : $this->domain;
    }


    /**
     * Set siteSize
     *
     * @param  string $siteSize
     * @return Web
     */
    public function setSiteSize($siteSize)
    {
        $this->siteSize = $siteSize;

        return $this;
    }

    /**
     * Get siteSize
     *
     * @return string $siteSize
     */
    public function getSiteSize()
    {
        return $this->siteSize;
    }

    /**
     * Set metaTitle
     *
     * @param  string $metaTitle
     * @return Web
     */
    public function setMetaTitle($metaTitle)
    {
        $this->metaTitle = $metaTitle;

        return $this;
    }

    /**
     * Get metaTitle
     *
     * @return string $metaTitle
     */
    public function getMetaTitle()
    {
        return $this->metaTitle;
    }

    /**
     * Set metaDescription
     *
     * @param  string $metaDescription
     * @return Web
     */
    public function setMetaDescription($metaDescription)
    {
        $this->metaDescription = $metaDescription;

        return $this;
    }

    /**
     * Get metaDescription
     *
     * @return string $metaDescription
     */
    public function getMetaDescription()
    {
        return $this->metaDescription;
    }

    /**
     * Set metaKeywords
     *
     * @param  string $metaKeywords
     * @return Web
     */
    public function setMetaKeywords($metaKeywords)
    {
        $this->metaKeywords = $metaKeywords;

        return $this;
    }

    /**
     * Get metaKeywords
     *
     * @return string $metaKeywords
     */
    public function getMetaKeywords()
    {
        return $this->metaKeywords;
    }

    /**
     * Set favicon
     *
     * @param  string $favicon
     * @return Web
     */
    public function setFavicon($favicon)
    {
        $this->favicon = $favicon;

        return $this;
    }

    /**
     * Get favicon
     *
     * @return string $favicon
     */
    public function getFavicon()
    {
        return $this->favicon;
    }

    /**
     * Set logoImage
     *
     * @param  string $logoImage
     * @return Web
     */
    public function setLogoImage($logoImage)
    {
        $this->logoImage = $logoImage;

        return $this;
    }

    /**
     * Get logoImage
     *
     * @return string $logoImage
     */
    public function getLogoImage()
    {
        return $this->logoImage;
    }

    /**
     * Set logoText
     *
     * @param  string $logoText
     * @return Web
     */
    public function setLogoText($logoText)
    {
        $this->logoText = $logoText;

        return $this;
    }

    /**
     * Get logoText
     *
     * @return string $logoText
     */
    public function getLogoText()
    {
        return $this->logoText;
    }

    /**
     * Set logoSize
     *
     * @param  string $logoSize
     * @return Web
     */
    public function setLogoSize($logoSize)
    {
        $this->logoSize = $logoSize;

        return $this;
    }

    /**
     * Get logoSize
     *
     * @return string $logoSize
     */
    public function getLogoSize()
    {
        return $this->logoSize;
    }

    /**
     * Set googleAnalytics
     *
     * @param  string $googleAnalytics
     * @return Web
     */
    public function setGoogleAnalytics($googleAnalytics)
    {
        $this->googleAnalytics = $googleAnalytics;

        return $this;
    }

    /**
     * Get googleAnalytics
     *
     * @return string $googleAnalytics
     */
    public function getGoogleAnalytics()
    {
        return $this->googleAnalytics;
    }

    /**
     * Set googleVerification
     *
     * @param  string $googleVerification
     * @return Web
     */
    public function setGoogleVerification($googleVerification)
    {
        $this->googleVerification = $googleVerification;

        return $this;
    }

    /**
     * Get googleVerification
     *
     * @return string $googleVerification
     */
    public function getGoogleVerification()
    {
        return $this->googleVerification;
    }

    /**
     * Set socialFacebook
     *
     * @param  string $socialFacebook
     * @return Web
     */
    public function setSocialFacebook($socialFacebook)
    {
        $this->socialFacebook = $socialFacebook;

        return $this;
    }

    /**
     * Get socialFacebook
     *
     * @return string $socialFacebook
     */
    public function getSocialFacebook()
    {
        return $this->socialFacebook;
    }

    /**
     * Set socialTwitter
     *
     * @param  string $socialTwitter
     * @return Web
     */
    public function setSocialTwitter($socialTwitter)
    {
        $this->socialTwitter = $socialTwitter;

        return $this;
    }

    /**
     * Get socialTwitter
     *
     * @return string $socialTwitter
     */
    public function getSocialTwitter()
    {
        return $this->socialTwitter;
    }

    /**
     * Set socialGooglePlus
     *
     * @param  string $socialGooglePlus
     * @return Web
     */
    public function setSocialGooglePlus($socialGooglePlus)
    {
        $this->socialGooglePlus = $socialGooglePlus;

        return $this;
    }

    /**
     * Get socialGooglePlus
     *
     * @return string $socialGooglePlus
     */
    public function getSocialGooglePlus()
    {
        return $this->socialGooglePlus;
    }

    public function setSocialVimeo($socialVimeo)
    {
        $this->socialVimeo = $socialVimeo;
    }

    public function getSocialVimeo()
    {
        return $this->socialVimeo;
    }

    /**
     * Set socialTuenti
     *
     * @param  string $socialTuenti
     * @return Web
     */
    public function setSocialTuenti($socialTuenti)
    {
        $this->socialTuenti = $socialTuenti;

        return $this;
    }

    /**
     * Get socialTuenti
     *
     * @return string $socialTuenti
     */
    public function getSocialTuenti()
    {
        return $this->socialTuenti;
    }

    /**
     * Set socialPinterest
     *
     * @param  string $socialPinterest
     * @return Web
     */
    public function setSocialPinterest($socialPinterest)
    {
        $this->socialPinterest = $socialPinterest;

        return $this;
    }

    /**
     * Get socialPinterest
     *
     * @return string $socialPinterest
     */
    public function getSocialPinterest()
    {
        return $this->socialPinterest;
    }

    /**
     * Set authorName
     *
     * @param  string $authorName
     * @return Web
     */
    public function setAuthorName($authorName)
    {
        $this->authorName = $authorName;

        return $this;
    }

    /**
     * Get authorName
     *
     * @return string $authorName
     */
    public function getAuthorName()
    {
        return $this->authorName;
    }

    /**
     * Set authorTelephone
     *
     * @param  string $authorTelephone
     * @return Web
     */
    public function setAuthorTelephone($authorTelephone)
    {
        $this->authorTelephone = $authorTelephone;

        return $this;
    }

    /**
     * Get authorTelephone
     *
     * @return string $authorTelephone
     */
    public function getAuthorTelephone()
    {
        return $this->authorTelephone;
    }

    /**
     * Set authorAddress
     *
     * @param  string $authorAddress
     * @return Web
     */
    public function setAuthorAddress($authorAddress)
    {
        $this->authorAddress = $authorAddress;

        return $this;
    }

    /**
     * Get authorAddress
     *
     * @return string $authorAddress
     */
    public function getAuthorAddress()
    {
        return $this->authorAddress;
    }

    /**
     * Set authorEmail
     *
     * @param  string $authorEmail
     * @return Web
     */
    public function setAuthorEmail($authorEmail)
    {
        $this->authorEmail = $authorEmail;

        return $this;
    }

    /**
     * Get authorEmail
     *
     * @return string $authorEmail
     */
    public function getAuthorEmail()
    {
        return $this->authorEmail;
    }

    public function setCreatedAt($createdAt)
    {
        $this->createdAt = $createdAt;
    }

    public function getCreatedAt()
    {
        return $this->createdAt;
    }

    public function setUpdatedAt($updatedAt)
    {
        $this->updatedAt = $updatedAt;
    }

    public function getUpdatedAt()
    {
        return $this->updatedAt;
    }

    public function setHeaderTemplate($headerTemplate)
    {
        $this->headerTemplate = $headerTemplate;
    }

    public function getHeaderTemplate()
    {
        return $this->headerTemplate;
    }

    public function setFooterTemplate($footerTemplate)
    {
        $this->footerTemplate = $footerTemplate;
    }

    public function getFooterTemplate()
    {
        return $this->footerTemplate;
    }

    public function setUserId($userId)
    {
        $this->userId = $userId;
    }

    public function getUserId()
    {
        return $this->userId;
    }

    public function setPiwikId($piwikId)
    {
        $this->piwikId = $piwikId;
    }

    public function getPiwikId()
    {
        return $this->piwikId;
    }

    public function addStyle($style)
    {
        $this->styles[] = $style;
    }

    public function removeStyle($style)
    {
        $this->styles->removeElement($style);
    }

    public function getStyles()
    {
        return $this->styles;
    }

    public function setStyles($styles)
    {
        $this->styles = $styles;
    }

    public function getHash()
    {
        //$url = "http://" . $this->getDefaultSubdomain();
        return /*$url .*/
            base64_encode(sha1($this->getId()));
    }

    public function getScreenshot($size = 'lo')
    {
        // devolver el lugar y nombre del archivo de imagen que se utiliza para
        // representar la imagen screenshoot
        return $this->getId() . '_' . $size . '.png';
    }

    public function setWebUserId($webUserId)
    {
        $this->webUserId = $webUserId;
    }

    public function getWebUserId()
    {
        return $this->webUserId;
    }

    public function setHtmlContentPreview($htmlContentPreview)
    {
        $this->htmlContentPreview = $htmlContentPreview;
    }

    public function getHtmlContentPreview()
    {
        return $this->htmlContentPreview;
    }

    public function addCustomStyles($customStyles)
    {
        $this->customStyles[] = $customStyles;
    }

    public function removeStyles($customStyles)
    {
        $this->customStyles->removeElement($customStyles);
    }

    public function getCustomStyles()
    {
        return $this->customStyles;
    }

    public function setCustomStyles($customStyles)
    {
        $this->customStyles = $customStyles;
    }

    /**
     * @param string $realmName
     */
    public function setRealmName($realmName)
    {
        $this->realmName = $realmName;
    }

    /**
     * @return string
     */
    public function getRealmName()
    {
        return $this->realmName;
    }

    public function setDemoId($demoId)
    {
        $this->demoId = $demoId;
    }

    public function getDemoId()
    {
        return $this->demoId;
    }

    public function setDemo($demo)
    {
        $this->demo = $demo;
    }

    /**
     * @return Demo
     */
    public function getDemo()
    {
        return $this->demo;
    }

    public function setPublishedAt($publishedAt)
    {
        $this->publishedAt = $publishedAt;
    }

    public function getPublishedAt()
    {
        return $this->publishedAt;
    }

    public function setInvalidateCache($invalidateCache)
    {
        $this->invalidateCache = $invalidateCache;
    }

    public function getInvalidateCache()
    {
        return $this->invalidateCache;
    }

}
