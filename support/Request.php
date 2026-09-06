<?php
/**
 * This file is part of webman.
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the MIT-LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @author    walkor<walkor@workerman.net>
 * @copyright walkor<walkor@workerman.net>
 * @link      http://www.workerman.net/
 * @license   http://www.opensource.org/licenses/mit-license.php MIT License
 */

namespace support;

/**
 * Class Request
 * @package support
 */
class Request extends \Webman\Http\Request
{
    /**
     * 判断请求中是否包含指定字段（webman 原生 Request 无 has()，
     * 各 controller 的 $request->has('field') 均依赖本方法）。
     *
     * 修复：原实现仅查 GET query，POST 体字段恒判 false（角色权限同步等
     * 走 POST/PUT 的字段被静默跳过）；现改查 input()（POST+GET 合并视图）。
     */
    public function has(string $key): bool
    {
        return $this->input($key) !== null;
    }
}