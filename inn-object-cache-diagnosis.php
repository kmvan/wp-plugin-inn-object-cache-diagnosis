<?php

// Plugin Name: INN Object Cache Diagnosis | INN 对象缓存医师
// Plugin URI: https://inn-studio.com/inn-object-cache-diagnosis
// Description: An Object-Cache diagnosis for WordPress. | 诊断您的 WordPress 对象缓存是否正常运作。
// Author: INN STUDIO
// Author URI: https://inn-studio.com
// Version: 1.0.1
// Required PHP: 7.3

declare(strict_types = 1);

namespace InnStudio\Plugins\InnObjectCacheDiagnosis;

\defined('AUTH_KEY') || \http_response_code(401) && die;

class InnObjectCacheDiagnosis
{
    const ID = 'innObjectCacheDiagnosis';

    const VERSION = '1.0.1';

    const CACHE_FILE_PATH = \WP_CONTENT_DIR . '/object-cache.php';

    private $actionId = '';

    public function __construct()
    {
        $this->actionId = \md5(self::ID);
        \add_action("wp_ajax_{$this->actionId}", [$this, 'filterAjax']);
        \add_filter('plugin_action_links', [$this, 'filterActionLink'], 10, 2);
    }

    public function filterAjax(): void
    {
        switch (\filter_input(\INPUT_GET, 'step', \FILTER_SANITIZE_STRING)) {
        case 'end':
            $this->ajaxEnd();

            // no break
        default:
            $this->ajaxStart();
        }

        die;
    }

    public function filterActionLink($actions, string $pluginFile): array
    {
        if (false !== \stripos($pluginFile, \basename(__DIR__))) {
            $adminUrl = get_admin_url();
            $opts     = <<<HTML
<a href="{$adminUrl}admin-ajax.php?action={$this->actionId}" target="_blank" class="button button-primary" style="line-height: 1.5; height: auto; min-height: unset">Detect | 开始诊断</a>
HTML;

            if ( ! \is_array($actions)) {
                $actions = [];
            }

            \array_unshift($actions, $opts);
        }

        return $actions;
    }

    private function getCurrentUrl(): string
    {
        $scheme = \is_ssl() ? 'https' : 'http';

        return "{$scheme}://{$_SERVER['HTTP_HOST']}{$_SERVER['REQUEST_URI']}";
    }

    private function checkCacheFileReadable(): void
    {
        if (\is_file(self::CACHE_FILE_PATH) && \is_readable(self::CACHE_FILE_PATH)) {
            return;
        }

        $this->output(\sprintf('Can not read object cache file %1$s.', self::CACHE_FILE_PATH), -1);
        $this->output(\sprintf('无法读取对象缓存文件 %1$s。', self::CACHE_FILE_PATH), -1);

        die;
    }

    private function getCacheType(): string
    {
        $content = \file_get_contents(self::CACHE_FILE_PATH);

        switch (true) {
        case false !== \stripos($content, 'new Memcached') || false !== \stripos($content, 'new \\Memcached'):
            return 'Memcached';

        case false !== \stripos($content, 'new Memcache') || false !== \stripos($content, 'new \\Memcache'):
            return 'Memcache';

        case false !== \stripos($content, 'filecache') || false !== \stripos($content, 'file cache') || false !== \stripos($content, 'cache-file'):
            return 'File Cache';

        case false !== \stripos($content, 'new Redis') || false !== \stripos($content, 'new \\Redis') || false !== \stripos($content, 'new Predis') || false !== \stripos($content, 'new \\Predis'):
            return 'Redis';

        case false !== \stripos($content, 'new SQLite3') || false !== \stripos($content, 'new \\SQLite3'):
            return 'SQLite3';

        default:
            return 'Unknow';
        }
    }

    private function ajaxStart(): void
    {
        $this->checkUser();
        $this->output('Starting ... ');
        $this->output('开始中……');
        $this->output('Checking object cache file readable ...');
        $this->output('正在检测对象缓存文件可读性……');
        $this->checkCacheFileReadable();
        $this->output('Object cache file is readable.', 1);
        $this->output('对象缓存文件可读。', 1);
        $this->output('Checking object cache type ...');
        $this->output('正在检测对象缓存类型……');

        $type       = $this->getCacheType();
        $typeStatus = 'Unknow' === $type ? -1 : 1;
        $this->output(\sprintf('Object cache type is: %1$s.', "<strong>{$type}</strong>"), $typeStatus);
        $this->output(\sprintf('对象缓存类型为: %1$s。', "<strong>{$type}</strong>"), $typeStatus);

        if (-1 === $typeStatus) {
            $this->output('Object cache is unknow, the test has been terminated.', -1);
            $this->output('未知类型对象缓存，测试终止。', -1);

            die;
        }
        $this->output('Starting cache test ...');
        $this->output('开始测试缓存……');
        $this->output('Try to set cache ...');
        $this->output('尝试设置缓存……');
        \wp_cache_set(self::VERSION, true, self::ID, \HOUR_IN_SECONDS);
        $this->output('Cache created, Please click next step ...');
        $this->output('缓存成功建立，请点击下一步……');

        $nextUrl = \add_query_arg([
            'step' => 'end',
        ], $this->getCurrentUrl());

        echo <<<HTML
<h1><a href="{$nextUrl}" style="color: white; background: green; text-decoration: none;">👉🏽 Next step | 下一步</a><h1>
HTML;

        die;
    }

    private function ajaxEnd(): void
    {
        $this->checkUser();
        $this->output('Checking previous cache ...');
        $this->output('正在检测上个缓存……');
        $exists = (bool) \wp_cache_get(self::VERSION, self::ID);

        $type = $this->getCacheType();

        if ($exists) {
            $this->output(\sprintf('Cache exists（%1$s), your object cache system works fine.', $type), 1);
            $this->output(\sprintf('缓存获取成功（%1$s），您的对象缓存系统运作正常。', $type), 1);
            \wp_cache_delete(self::VERSION, self::ID);
        } else {
            $this->output(\sprintf('Cache not found (%1$s), your object cache system does NOT work fine.', $type), -1);
            $this->output(\sprintf('缓存获取失败（%1$s），您的对象缓存系统运作异常，请截图并联系技术支持以解决。', $type), -1);
        }

        echo <<<'HTML'
<p><button onClick="window.open(false, '_self', false);window.close();">Done, click to close this page | 完成，点击关闭此页面</button></p>
HTML;

        die;
    }

    private function output(string $str, int $status = 0): void
    {
        $icon  = '⏳';
        $color = 'black';

        switch (true) {
        case 1 === $status:
            $icon  = '✔️';
            $color = 'green';

            break;

        case -1 === $status:
            $icon  = '✖️';
            $color = 'red';

            break;
        }

        $color = <<<CSS
style="color: {$color};"
CSS;

        echo <<<HTML
<div {$color}>{$icon} {$str}</div>
HTML;
    }

    private function checkUser(): void
    {
        if (\current_user_can('manage_options')) {
            return;
        }

        die('Insufficient permissions | 权限不足');
    }
}

new InnObjectCacheDiagnosis();
