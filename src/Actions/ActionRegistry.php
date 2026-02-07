<?php

declare(strict_types=1);

namespace KalimeroMK\SeoReport\Actions;

use KalimeroMK\SeoReport\Actions\Misc\CharsetAction;
use KalimeroMK\SeoReport\Actions\Misc\ContentLengthAction;
use KalimeroMK\SeoReport\Actions\Misc\DeprecatedHtmlTagsAction;
use KalimeroMK\SeoReport\Actions\Misc\FlashContentAction;
use KalimeroMK\SeoReport\Actions\Misc\IframesAction;
use KalimeroMK\SeoReport\Actions\Misc\InlineCssAction;
use KalimeroMK\SeoReport\Actions\Misc\LlmsTxtAction;
use KalimeroMK\SeoReport\Actions\Misc\MetaViewportAction;
use KalimeroMK\SeoReport\Actions\Misc\SitemapAction;
use KalimeroMK\SeoReport\Actions\Misc\SocialLinksAction;
use KalimeroMK\SeoReport\Actions\Misc\AccessibilityAction;
use KalimeroMK\SeoReport\Actions\Misc\StructuredDataAction;
use KalimeroMK\SeoReport\Actions\Misc\StructuredDataValidationAction;
use KalimeroMK\SeoReport\Actions\Misc\TextHtmlRatioAction;
use KalimeroMK\SeoReport\Actions\Performance\CacheHeadersAction;
use KalimeroMK\SeoReport\Actions\Performance\CompressionAction;
use KalimeroMK\SeoReport\Actions\Performance\CookieFreeDomainsAction;
use KalimeroMK\SeoReport\Actions\Performance\DeferJavascriptAction;
use KalimeroMK\SeoReport\Actions\Performance\DoctypeAction;
use KalimeroMK\SeoReport\Actions\Performance\DomSizeAction;
use KalimeroMK\SeoReport\Actions\Performance\EmptySrcHrefAction;
use KalimeroMK\SeoReport\Actions\Performance\HttpRequestsAction;
use KalimeroMK\SeoReport\Actions\Performance\ResourceHintsAction;
use KalimeroMK\SeoReport\Actions\Performance\ImageOptimizationAction;
use KalimeroMK\SeoReport\Actions\Performance\MinificationAction;
use KalimeroMK\SeoReport\Actions\Performance\PageSizeAction;
use KalimeroMK\SeoReport\Actions\Performance\RedirectsAction;
use KalimeroMK\SeoReport\Actions\Performance\RenderBlockingResourcesAction;
use KalimeroMK\SeoReport\Actions\Performance\TimingAction;
use KalimeroMK\SeoReport\Actions\Security\Http2Action;
use KalimeroMK\SeoReport\Actions\Security\HttpsEncryptionAction;
use KalimeroMK\SeoReport\Actions\Security\HstsAction;
use KalimeroMK\SeoReport\Actions\Security\MixedContentAction;
use KalimeroMK\SeoReport\Actions\Security\PlaintextEmailAction;
use KalimeroMK\SeoReport\Actions\Security\SecurityHeadersAction;
use KalimeroMK\SeoReport\Actions\Security\ServerSignatureAction;
use KalimeroMK\SeoReport\Actions\Security\UnsafeCrossOriginLinksAction;
use KalimeroMK\SeoReport\Actions\Seo\CanonicalAction;
use KalimeroMK\SeoReport\Actions\Seo\DuplicateContentAction;
use KalimeroMK\SeoReport\Actions\Seo\InternalLinkingAction;
use KalimeroMK\SeoReport\Actions\Seo\ContentKeywordsAction;
use KalimeroMK\SeoReport\Actions\Seo\FaviconAction;
use KalimeroMK\SeoReport\Actions\Seo\ContentQualityAction;
use KalimeroMK\SeoReport\Actions\Seo\HeadingsAction;
use KalimeroMK\SeoReport\Actions\Seo\HreflangAction;
use KalimeroMK\SeoReport\Actions\Seo\InternationalSeoAction;
use KalimeroMK\SeoReport\Actions\Seo\PaginationAction;
use KalimeroMK\SeoReport\Actions\Seo\ImageKeywordsAction;
use KalimeroMK\SeoReport\Actions\Seo\InPageLinksAction;
use KalimeroMK\SeoReport\Actions\Seo\LanguageAction;
use KalimeroMK\SeoReport\Actions\Seo\MobileUsabilityAction;
use KalimeroMK\SeoReport\Actions\Seo\LinkUrlReadabilityAction;
use KalimeroMK\SeoReport\Actions\Seo\MetaDescriptionAction;
use KalimeroMK\SeoReport\Actions\Seo\NofollowLinksAction;
use KalimeroMK\SeoReport\Actions\Seo\NoindexHeaderAction;
use KalimeroMK\SeoReport\Actions\Seo\NotFoundAction;
use KalimeroMK\SeoReport\Actions\Seo\OpenGraphAction;
use KalimeroMK\SeoReport\Actions\Seo\RobotsAction;
use KalimeroMK\SeoReport\Actions\Seo\SeoFriendlyUrlAction;
use KalimeroMK\SeoReport\Actions\Seo\UrlStructureAction;
use KalimeroMK\SeoReport\Actions\Seo\TitleAction;
use KalimeroMK\SeoReport\Actions\Seo\TwitterCardsAction;
use KalimeroMK\SeoReport\Actions\Technology\AnalyticsAction;
use KalimeroMK\SeoReport\Actions\Technology\DmarcRecordAction;
use KalimeroMK\SeoReport\Actions\Technology\DnsServersAction;
use KalimeroMK\SeoReport\Actions\Technology\ReverseDnsAction;
use KalimeroMK\SeoReport\Actions\Technology\ServerIpAction;
use KalimeroMK\SeoReport\Actions\Technology\SpfRecordAction;
use KalimeroMK\SeoReport\Actions\Technology\SslCertificateAction;
use KalimeroMK\SeoReport\Actions\Technology\TechnologyDetectionAction;

final class ActionRegistry
{
    /** @return list<AnalysisActionInterface> */
    public function seo(): array
    {
        return [
            new TitleAction(),
            new MetaDescriptionAction(),
            new HeadingsAction(),
            new ContentKeywordsAction(),
            new ImageKeywordsAction(),
            new InPageLinksAction(),
            new LinkUrlReadabilityAction(),
            new NofollowLinksAction(),
            new OpenGraphAction(),
            new TwitterCardsAction(),
            new SeoFriendlyUrlAction(),
            new UrlStructureAction(),
            new CanonicalAction(),
            new HreflangAction(),
            new InternationalSeoAction(),
            new PaginationAction(),
            new NotFoundAction(),
            new RobotsAction(),
            new NoindexHeaderAction(),
            new LanguageAction(),
            new FaviconAction(),
            new DuplicateContentAction(),
            new InternalLinkingAction(),
            new MobileUsabilityAction(),
            new ContentQualityAction(),
        ];
    }

    /** @return list<AnalysisActionInterface> */
    public function performance(): array
    {
        return [
            new CompressionAction(),
            new TimingAction(),
            new PageSizeAction(),
            new HttpRequestsAction(),
            new ResourceHintsAction(),
            new CacheHeadersAction(),
            new RedirectsAction(),
            new CookieFreeDomainsAction(),
            new EmptySrcHrefAction(),
            new ImageOptimizationAction(),
            new DeferJavascriptAction(),
            new RenderBlockingResourcesAction(),
            new MinificationAction(),
            new DomSizeAction(),
            new DoctypeAction(),
        ];
    }

    /** @return list<AnalysisActionInterface> */
    public function security(): array
    {
        return [
            new HttpsEncryptionAction(),
            new Http2Action(),
            new MixedContentAction(),
            new ServerSignatureAction(),
            new UnsafeCrossOriginLinksAction(),
            new HstsAction(),
            new PlaintextEmailAction(),
            new SecurityHeadersAction(),
        ];
    }

    /** @return list<AnalysisActionInterface> */
    public function misc(): array
    {
        return [
            new StructuredDataAction(),
            new StructuredDataValidationAction(),
            new MetaViewportAction(),
            new CharsetAction(),
            new SitemapAction(),
            new SocialLinksAction(),
            new AccessibilityAction(),
            new ContentLengthAction(),
            new TextHtmlRatioAction(),
            new InlineCssAction(),
            new DeprecatedHtmlTagsAction(),
            new LlmsTxtAction(),
            new FlashContentAction(),
            new IframesAction(),
        ];
    }

    /** @return list<AnalysisActionInterface> */
    public function technology(): array
    {
        return [
            new ServerIpAction(),
            new DnsServersAction(),
            new DmarcRecordAction(),
            new SpfRecordAction(),
            new SslCertificateAction(),
            new ReverseDnsAction(),
            new AnalyticsAction(),
            new TechnologyDetectionAction(),
        ];
    }
}
