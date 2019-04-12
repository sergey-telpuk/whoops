<?php
/**
 * Whoops - php errors for cool kids
 * @author Filipe Dobreira <http://github.com/filp>
 */

namespace Middlewares\Handler;

use InvalidArgumentException;
use RuntimeException;
use Symfony\Component\VarDumper\Cloner\AbstractCloner;
use Symfony\Component\VarDumper\Cloner\VarCloner;
use UnexpectedValueException;
use Whoops\Exception\Formatter;
use Whoops\Handler\Handler;
use Whoops\Handler\PlainTextHandler;
use Whoops\Util\Misc;
use Whoops\Util\TemplateHelper;
use Whoops\Handler\PrettyPageHandler as BasePrettyPageHandler;

class PrettyPageHandler extends BasePrettyPageHandler
{
    public function handle()
    {
        $templateFile = $this->getResource("views/layout.html.php");
        $cssFile      = $this->getResource("css/whoops.base.css");
        $zeptoFile    = $this->getResource("js/zepto.min.js");
        $prettifyFile = $this->getResource("js/prettify.min.js");
        $clipboard    = $this->getResource("js/clipboard.min.js");
        $jsFile       = $this->getResource("js/whoops.base.js");

        if ($this->customCss) {
            $customCssFile = $this->getResource($this->customCss);
        }

        $inspector = $this->getInspector();
        $frames = $this->getExceptionFrames();
        $code = $this->getExceptionCode();

        // List of variables that will be passed to the layout template.
        $vars = [
            "page_title" => $this->getPageTitle(),

            // @todo: Asset compiler
            "stylesheet" => file_get_contents($cssFile),
            "zepto"      => file_get_contents($zeptoFile),
            "prettify"   => file_get_contents($prettifyFile),
            "clipboard"  => file_get_contents($clipboard),
            "javascript" => file_get_contents($jsFile),

            // Template paths:
            "header"                     => $this->getResource("views/header.html.php"),
            "header_outer"               => $this->getResource("views/header_outer.html.php"),
            "frame_list"                 => $this->getResource("views/frame_list.html.php"),
            "frames_description"         => $this->getResource("views/frames_description.html.php"),
            "frames_container"           => $this->getResource("views/frames_container.html.php"),
            "panel_details"              => $this->getResource("views/panel_details.html.php"),
            "panel_details_outer"        => $this->getResource("views/panel_details_outer.html.php"),
            "panel_left"                 => $this->getResource("views/panel_left.html.php"),
            "panel_left_outer"           => $this->getResource("views/panel_left_outer.html.php"),
            "frame_code"                 => $this->getResource("views/frame_code.html.php"),
            "env_details"                => $this->getResource("views/env_details.html.php"),

            "title"            => $this->getPageTitle(),
            "name"             => explode("\\", $inspector->getExceptionName()),
            "message"          => $inspector->getExceptionMessage(),
            "previousMessages" => $inspector->getPreviousExceptionMessages(),
            "docref_url"       => $inspector->getExceptionDocrefUrl(),
            "code"             => $code,
            "previousCodes"    => $inspector->getPreviousExceptionCodes(),
            "plain_exception"  => Formatter::formatExceptionPlain($inspector),
            "frames"           => $frames,
            "has_frames"       => !!count($frames),
            "handler"          => $this,
            "handlers"         => $this->getRun()->getHandlers(),

            "active_frames_tab" => count($frames) && $frames->offsetGet(0)->isApplication() ?  'application' : 'all',
            "has_frames_tabs"   => $this->getApplicationPaths(),

            "tables"      => [
                "GET Data"              => $this->masked($_GET, '_GET'),
                "POST Data"             => $this->masked($_POST, '_POST'),
                "Files"                 => isset($_FILES) ? $this->masked($_FILES, '_FILES') : [],
                "Cookies"               => $this->masked($_COOKIE, '_COOKIE'),
                "Session"               => isset($_SESSION) ? $this->masked($_SESSION, '_SESSION') :  [],
                "Server/Request Data"   => $this->masked($_SERVER, '_SERVER'),
                "Environment Variables" => $this->masked($_ENV, '_ENV'),
            ],
        ];

        if (isset($customCssFile)) {
            $vars["stylesheet"] .= file_get_contents($customCssFile);
        }

        // Add extra entries list of data tables:
        // @todo: Consolidate addDataTable and addDataTableCallback
        $extraTables = array_map(function ($table) use ($inspector) {
            return $table instanceof \Closure ? $table($inspector) : $table;
        }, $this->getDataTables());
        $vars["tables"] = array_merge($extraTables, $vars["tables"]);

        $plainTextHandler = new PlainTextHandler();
        $plainTextHandler->setException($this->getException());
        $plainTextHandler->setInspector($this->getInspector());
        $vars["preface"] = "<!--\n\n\n" .  $this->templateHelper->escape($plainTextHandler->generateResponse()) . "\n\n\n\n\n\n\n\n\n\n\n-->";

        $this->templateHelper->setVariables($vars);
        $this->templateHelper->render($templateFile);

        return Handler::QUIT;
    }
}
