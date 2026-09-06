/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

/**
 * 运行期字符串解析工具（仅插值串用）：
 * 页面静态文案优先 $r（资源随首选语言自动解析）；含 %s/%d 占位符的串无法用 $r
 * 直取，需经本工具按资源名解析并代入参数（按当前首选语言返回）。
 *
 * @param ui 调用方 UIContext（组件内 this.getUIContext()）
 * @param key 资源名（base/en_US string.json 中的 name）
 * @param args 占位符实参；串无占位符时省略，勿传
 * @returns 当前首选语言下的字符串；上下文/资源不可用时回退 key 本身，保证不崩
 */
export class L10n {
  static str(ui: UIContext, key: string, ...args: Array<string | number>): string {
    try {
      const host = ui.getHostContext();
      if (host) {
        const rm = host.resourceManager;
        if (args.length > 0) {
          return rm.getStringByNameSync(key, ...args);
        }
        return rm.getStringByNameSync(key);
      }
    } catch (err) {
      // 上下文/资源不可用：回退 key 文本，页面不崩
    }
    return key;
  }
}
