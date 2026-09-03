<?php
/*
 * 欣欣聊天室  —  StellaFortuna-Chat  单文件应用
 * =============================================================
 * 猎户座编辑器(Orion Composer)格式的富文本聊天室。
 * 黑白极简、方角边框、消息分片加载、JS轮询实时刷新。
 * -------------------------------------------------------------
 * 账户 / 密码（硬编码在本文件头部）
 */
const ORION_ACCOUNTS = [
    // code => [显示名, 密码, 是否可发消息]，密码需唯一。
    'altzin' => ['Altzin',    '67890',                true],
    'lian'   => ['林可欣',     '12345',                    true],
    'guest'  => ['访客',       '00000', false],
    //'laji'  => ['垃圾',       '55555', true],
];

const DATA_DIR = __DIR__ . '/data';
const POLL_SECONDS = 2;      // 轮询间隔

date_default_timezone_set('Asia/Shanghai');
mb_internal_encoding('UTF-8');
// 关闭错误显示，避免污染 JSON 输出（日志仍会记录）
error_reporting(0);
ini_set('display_errors', '0');

const __APP_HTML__ = <<<'APPHTML'
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title>欣欣聊天室 · StellaFortuna-Chat</title>
<link rel="stylesheet" href="lib/highlight.css">
<style>
:root{
  --bg:#ffffff;          /* 极简黑白 */
  --bg2:#f4f4f4;
  --ink:#111111;
  --muted:#8a8a8a;
  --line:#222222;
  --line-soft:#d8d8d8;
  --font:'LXGW WenKai TC','KaiTi','Noto Serif SC',serif;
}
*{box-sizing:border-box}
html,body{width:100%;height:100%;margin:0;overflow:hidden}
body{
  background:var(--bg);color:var(--ink);
  font-family:var(--font);
  -webkit-tap-highlight-color:transparent;
}
button,input,textarea{font:inherit;color:inherit}
button{touch-action:manipulation}

/* ---------- 登录页 / Safari 屏蔽页 ---------- */
#login,#safariBlock{
  position:fixed;inset:0;display:flex;flex-direction:column;
  align-items:center;justify-content:center;gap:26px;
  background:var(--bg);z-index:50;
  /* 安全区适配（刘海/手势条），兼容不支持 env() 的浏览器 */
  padding-top:max(20px, env(safe-area-inset-top, 0px));
  padding-bottom:max(20px, env(safe-area-inset-bottom, 0px));
  padding-left:max(20px, env(safe-area-inset-left, 0px));
  padding-right:max(20px, env(safe-area-inset-right, 0px));
}
#safariBlock{display:none}
#safariBlock.show{display:flex}
#login .brand,#safariBlock .brand{text-align:center}
#login h1,#safariBlock h1{font-size:30px;letter-spacing:.12em;margin:0 0 10px}
#login p,#safariBlock p{color:var(--muted);font-size:13px;margin:0}
#login form{
  display:flex;flex-direction:column;gap:12px;width:min(320px,100%);
}
#login input[type=password]{
  height:46px;padding:0 14px;border:2px solid var(--line);
  border-radius:0;background:var(--bg);outline:none;font-size:15px;
}
#login input[type=password]:focus{border-width:2px}
#login button{
  height:46px;border:2px solid var(--line);border-radius:0;
  background:var(--ink);color:#fff;font-size:15px;cursor:pointer;
  letter-spacing:.2em;
}
#safariBlock .msg{
  width:min(320px,100%);border:2px solid var(--line);
  padding:16px 14px;font-size:14px;line-height:1.8;text-align:center;
  color:var(--ink);background:var(--bg);
}
#login .hint,#safariBlock .hint{color:var(--muted);font-size:12px;text-align:center;line-height:1.7}
#login .err{color:var(--line);font-size:12px;text-align:center;min-height:16px}

/* ---------- 主界面 ----------
   根容器统一加底部安全区内边距：整块界面（含发送栏）整体避开底部 home 手势条。
   env() 及 fallback 语法在 Chrome 69+ 均可用（103 没问题）。 */
#app{display:none;flex-direction:column;height:100%;padding-bottom:env(safe-area-inset-bottom, 0px)}
#app.on{display:flex}
.top{
  display:flex;align-items:center;gap:12px;
  /* 顶栏避开顶部刘海/home指示条，并留出左右安全边距 */
  padding-top:max(10px, env(safe-area-inset-top, 0px));
  padding-bottom:10px;
  padding-left:max(14px, env(safe-area-inset-left, 0px));
  padding-right:max(14px, env(safe-area-inset-right, 0px));
  border-bottom:2px solid var(--line);
  background:var(--bg);flex:0 0 auto;
}
.top .room{font-size:16px;font-weight:bold;letter-spacing:.06em}
.top .who{margin-left:auto;font-size:13px;color:var(--muted)}
.top .topSearch{position:relative;flex:0 1 240px;min-width:120px}
.top .topSearch input[type=search]{
  width:100%;height:30px;padding:0 8px;border:1px solid var(--line-soft);border-radius:0;
  background:var(--bg);color:var(--ink);outline:none;font-size:12px;
}
.top .topSearch input[type=search]:focus{border-color:var(--line)}
.top .searchResults{
  position:absolute;top:calc(100% + 4px);left:0;z-index:70;width:100%;max-width:100%;box-sizing:border-box;
  max-height:280px;overflow:auto;overflow-x:hidden;
  background:var(--bg);border:1px solid var(--line);box-shadow:0 8px 24px rgba(0,0,0,.18);
}
.top .searchResults[hidden]{display:none}
.top .searchResults .sr{display:block;width:100%;box-sizing:border-box;text-align:left;padding:7px 9px;border:none;border-bottom:1px solid var(--line-soft);
  background:transparent;color:var(--ink);cursor:pointer;font-size:12px;line-height:1.5;overflow-wrap:break-word;word-break:break-word;}
.top .searchResults .sr .srM{display:block;color:var(--muted);font-size:11px;overflow-wrap:anywhere;word-break:break-word}
.top .searchResults .srE{display:block;padding:7px 9px;font-size:12px;color:var(--muted);overflow-wrap:anywhere;word-break:break-word;text-align:left;line-height:1.5}
.top .searchResults .sr mark{background:rgba(255,213,0,.55);color:inherit;padding:0 1px;border-radius:0}
.top button{
  border:1px solid var(--line);border-radius:0;background:var(--bg);
  padding:5px 12px;cursor:pointer;font-size:12px;
}

#chat{
  flex:1 1 auto;min-height:0;overflow-y:auto;overflow-x:hidden;
  padding-top:16px;
  padding-bottom:8px;
  padding-left:max(12px, env(safe-area-inset-left, 0px));
  padding-right:max(12px, env(safe-area-inset-right, 0px));
  display:flex;flex-direction:column;gap:14px;
}

/* 消息行 —— 我方在右 / 对方在左；访客一律在左
   min-width:0 允许 flex 子项收缩，长连续串也能在满 86% 处自动换行而不是横向溢出 */
.msg{display:flex;flex-direction:column;max-width:86%;min-width:0}
.msg.mine{align-self:flex-end;align-items:flex-end;text-align:right}
.msg.other{align-self:flex-start;align-items:flex-start;text-align:left}
.msg .content{min-width:0;max-width:100%}
.msg .meta{
  font-size:11px;color:var(--muted);margin:0 6px 4px;
  display:flex;align-items:center;gap:8px;max-width:100%;
}
.msg.mine .meta{flex-direction:row-reverse}
.msg .meta .name{font-weight:bold;color:var(--ink);white-space:nowrap}
.msg .meta time{white-space:nowrap;opacity:.8}
.msg .meta .copySrc{
  border:none;background:none;padding:0;cursor:pointer;color:var(--muted);
  text-decoration:underline;text-underline-offset:2px;font-size:11px;
}
.msg .meta .msgid{color:var(--muted);opacity:.75}
.msg .meta .quoteSrc{
  border:none;background:none;padding:0;cursor:pointer;color:var(--muted);
  text-decoration:underline dotted;text-underline-offset:2px;font-size:11px;
}

/* 引用：链接样式的块，显示被引用消息前 10 实字 + … */
.rich .msgquote{
  display:inline-block;margin:2px 0;padding:1px 4px;cursor:pointer;
  color:#0969da;text-decoration:underline;text-underline-offset:2px;
  border:1px dashed var(--line-soft);background:var(--bg2);border-radius:0;
  font-size:13px;word-break:break-word;
}
.rich .msgquote:before{content:"引用 ";color:var(--muted);text-decoration:none;font-size:11px}
.rich .msgquote.loading{color:var(--muted);text-decoration:none}
/* 被引用的消息已删除/无法加载：不可点击，不再像链接 */
.rich .msgquote.removed{
  cursor:default; color:var(--muted); text-decoration:none; pointer-events:none;
}

/* 跳转/搜索落到目标消息时的高亮（1 秒） */
#chat .msg.highlight{background:rgba(9,105,218,.16);outline:2px solid #0969da;outline-offset:2px;transition:background .4s ease}
/* 气泡透明不可见：无边框、无背板、无内边距，文字用常规墨色 */
.msg .bubble{
  position:relative;background:transparent;border:none;padding:0;
  max-width:100%;min-width:0;
  overflow-wrap:anywhere;word-break:break-word;
  color:var(--ink);font-size:14.5px;line-height:1.65;
}
.msg .bubble:empty{display:none}
.msg .del{
  margin-top:4px;font-size:11px;color:var(--muted);border:none;
  background:none;cursor:pointer;text-decoration:underline;padding:0;
}

/* ---------- 猎户座编辑器格式的富文本渲染（对齐 Orion 源码样式） ---------- */
.rich h1,.rich h2,.rich h3{
  padding-bottom:.3em;border-bottom:1px solid var(--line-soft);margin:.6em 0 .5em;
  line-height:1.35;
}
.rich h1{font-size:22px}.rich h2{font-size:18px}.rich h3{font-size:16px}
.rich p{margin:.5em 0}
.rich a{color:#0969da}
.msg.mine .rich a{color:#0969da}
.rich a.uclink{color:#0969da;text-decoration:underline;text-underline-offset:2px;word-break:break-all}
.rich blockquote{
  margin:8px 0;padding:6px 12px;border-left:4px solid var(--line);
  background:var(--bg2);color:#333;
}
.rich ul,.rich ol{padding-left:26px;margin:.5em 0}
.rich li{margin:.15em 0}
.rich code:not(.hljs){
  padding:.15em .4em;border:1px solid var(--line-soft);border-radius:0;
  background:var(--bg2);color:var(--ink);font-family:Menlo,Consolas,monospace;font-size:85%;
}
.rich pre{
  max-width:100%;margin:.6em 0;padding:12px;overflow:auto;
  border:1px solid var(--line-soft);border-radius:0;background:#f6f6f6;color:var(--ink);
}
.rich pre code{border:none;background:transparent;padding:0}
/* 代码块：默认折叠只显示约 20 行，带语言标签 + 复制 + 展开按钮 */
.rich .codeblock{
  border:1px solid var(--line-soft);background:#f6f6f6;margin:.6em 0;
  display:flex;flex-direction:column;
}
.rich .codeblock-bar{
  display:flex;align-items:center;gap:8px;padding:5px 8px;
  border-bottom:1px solid var(--line-soft);background:var(--bg);
  font-size:11px;flex:0 0 auto;
}
.rich .codeblock-lang{color:var(--muted);font-size:11px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.rich .codeblock-bar button{
  border:1px solid var(--line-soft);background:var(--bg);color:var(--ink);
  cursor:pointer;font-size:11px;padding:2px 9px;border-radius:0;line-height:1.4;
}
.rich .codeblock-bar .codeblock-copy{margin-left:auto}
.rich .codeblock pre{
  margin:0;border:none;background:transparent;padding:10px 12px;overflow:auto;flex:1 1 auto;
}
.rich .codeblock pre code{display:block}
/* 折叠态：最多显示 14 个视觉行，最后 3 行开始渐隐，渐隐末端更浅（更透） */
.rich .codeblock.collapsed pre{
  max-height:calc(14 * 1.5em);
  overflow:hidden;
  position:relative;
}
.rich .codeblock.collapsed pre::after{
  content:"";position:absolute;left:0;right:0;bottom:0;height:calc(3 * 1.5em);
  background:linear-gradient(180deg, rgba(246,246,246,0), rgba(246,246,246,.72));
  pointer-events:none;
}
.rich .codeblock.collapsed .codeblock-toggle::before{content:"展开 "}
.rich .codeblock:not(.collapsed) .codeblock-toggle::before{content:"收起 "}
.rich table{width:100%;border-collapse:collapse;margin:.6em 0}
.rich th,.rich td{padding:7px 9px;border:1px solid var(--line-soft);overflow-wrap:anywhere}
.rich th{background:var(--bg2)}
.rich span.spoiler{display:inline}
.rich div.spoiler{display:block;width:max-content;max-width:100%}
/* 剧透：外层仅负责“收口”——overflow:hidden 把内部模糊在两个维度都截断在内容边缘，
   因此边界与原来内容所占大小完全一致，不往周围泄漏，也不额外画边框/加底色。
   注意：行内剧透外层用 display:inline（而非 inline-block），否则块盒会让同一行
   前后的文字基线上窜、出现“后面的字更高”的错位；图片通常走块级剧透，仍靠 overflow 收口 */
.rich .spoiler{
  position:relative;
  cursor:pointer;-webkit-user-select:none;user-select:none;
  overflow:hidden;
}
.rich span.spoiler-core{display:inline-block;vertical-align:baseline}
/* 文字/emoji 用很小的模糊(2px)，几乎只是轻微虚一点；
   图片单独叠加更大的模糊，确保剧透里的大图也被遮严实。
   transition 让点击揭示时由模糊平滑动到清晰（需配合移动偏好自动关闭） */
.rich .spoiler-core{
  -webkit-filter:blur(0.2em);
  filter:blur(0.2em);
  -webkit-transition:-webkit-filter .28s ease;
  transition:filter .28s ease;
}
.rich .spoiler .spoiler-core img{
  -webkit-filter:blur(9px);
  filter:blur(9px);
  -webkit-transition:-webkit-filter .28s ease;
  transition:filter .28s ease;
}
.rich .spoiler.revealed{
  -webkit-user-select:text;user-select:text;
}
.rich .spoiler.revealed .spoiler-core,
.rich .spoiler.revealed .spoiler-core img{
  -webkit-filter:none;filter:none;
}
.rich details{margin:8px 0;padding:8px;border:1px solid var(--line-soft)}
.rich summary{font-weight:bold;cursor:pointer}
.rich mark{background:rgba(255,213,0,.45);color:inherit;padding:0 1px}
.rich img { --scale: 1; display:block;
  width: min(100%, 100% * var(--scale), 600px * var(--scale));
  height: auto; margin:.6em 0; border:1px solid var(--line-soft) }
/* 只隐藏图片后面紧跟的第一个 <br>（相邻兄弟选择器只匹配一个）：
   块级图片本身已断行，紧邻的 <br> 只会多出一行空行高，导致图片下方间距大于上方；
   多换行时其余 <br> 保留。图片前面的 <br> 不处理（br+img 会作用到图片自身，不可用） */
.rich img + br{display:none}
/* 自己发的（右侧）：被 600px 限制的图片靠右 */
.msg.mine .rich img{margin-left:auto;margin-right:0}
.rich hr{border:none;border-top:2px solid var(--line);margin:.9em 0}
.rich [data-theme-scrollable="true"]{
  max-height:260px;padding:10px;overflow:auto;border:1px solid var(--line-soft);
  background:var(--bg2);scrollbar-width:thin;
}
.rich .orion-plain-box{
  padding:10px;border:1px solid var(--line-soft);background:var(--bg2);
  margin:.6em 0;
}
.rich .footnotes{margin-top:1.6em;padding-top:.9em;border-top:1px solid var(--line-soft);font-size:.92em;color:var(--muted)}
.rich .footnote-ref a,.rich .footnote-backref{text-decoration:none;margin-left:2px}
.rich s, .rich del{text-decoration:line-through}
/* 代码高亮（由本地 highlight.css 处理配色，这里仅保证在消息区域可读） */
.rich pre code.hljs{background:transparent}

/* 分片加载状态 */
#chat .loader{
  align-self:center;color:var(--muted);font-size:12px;letter-spacing:.08em;
}
#chat .loader button{
  border:1px solid var(--line);border-radius:0;background:var(--bg);
  padding:4px 12px;cursor:pointer;font-size:12px;
}
#chat .system{
  align-self:center;color:var(--muted);font-size:12px;
  border:1px dashed var(--line-soft);padding:3px 12px;border-radius:0;
}
#chat .gap{
  align-self:center;color:var(--muted);font-size:12px;letter-spacing:.2em;
  display:flex;align-items:center;gap:10px;
}
#chat .gap::before,#chat .gap::after{content:"";height:1px;width:26px;background:var(--line-soft)}
.newMsg{
  /* 挂到 body，用 position:fixed 定位在聊天区底部中央（JS 计算）；外观与原来一致 */
  position:fixed;
  border:none;background:transparent;color:var(--ink);cursor:pointer;
  text-decoration:underline;text-underline-offset:3px;font-size:12.5px;letter-spacing:.15em;
  padding:6px 16px;border:1px solid var(--line);background:var(--bg);
}

/* ---------- 编辑器（猎户座编辑器功能面板，黑白方角） ---------- */
#composer{flex:0 0 auto;border-top:2px solid var(--line);background:var(--bg)}
#composer:not(.open){display:none}
#composer.open{display:block}
.toolbar{
  display:flex;flex-wrap:wrap;gap:4px;padding:6px 8px;
  border-bottom:1px solid var(--line-soft);font-size:12px;
}
.toolbar button{
  border:1px solid var(--line-soft);border-radius:0;background:var(--bg);
  padding:4px 9px;cursor:pointer;min-height:30px;white-space:nowrap;
}
.toolbar .sep{width:1px;background:var(--line-soft);margin:0 4px;align-self:stretch}

/* 编辑区：左侧输入，右侧实时预览（响应式堆叠）。
   两个面板在此横向并排，flex 默认 align-items:stretch，保证预览区与文本框高度始终相同。 */
.editorArea{
  display:flex;align-items:stretch;width:100%;height:min(44vh,360px);min-height:150px;
  border-bottom:1px solid var(--line-soft);
}
.editorArea .pane{flex:1 1 50%;min-width:0;height:auto;overflow:auto;padding:10px 14px}
.editorArea .pane::-webkit-scrollbar{width:6px;height:6px}
.editorArea .pane::-webkit-scrollbar-thumb{background:var(--line-soft);border-radius:0}
.editorArea .input-pane{padding:0}
#editor{
  display:block;width:100%;height:100%;border:none;outline:none;resize:none;
  padding:10px 14px;
  font-family:Menlo,Consolas,monospace;font-size:13.5px;line-height:1.6;
  background:var(--bg);color:var(--ink);
}
#editor::placeholder{color:var(--muted)}
.preview-pane{border-left:1px solid var(--line-soft);background:var(--bg2)}
.preview-pane:empty::before{content:"预览区";color:var(--muted);font-size:12px}
.preview-pane .preview{min-height:100%;font-size:14px;line-height:1.7;overflow-wrap:anywhere}

/* 编辑器打开时（高度充足）：聊天区保持 flex:1 仍占满剩余高度，编辑器保持其常规高度。
   是否“高度不足”由 JS 实测消息区可用像素决定：当消息区可用高度过少时，
   由 JS 给 #app 加 editor-full 类 → 隐藏聊天、让编辑器独占并允许压缩。
   注意：即使高度不足，输入框与预览区仍需同时显示，且两者高度相同。 */
#app.editor-full #chat{display:none}
#app.editor-full #composer.open{display:flex;flex-direction:column;flex:1 1 auto;min-height:0}
#app.editor-full .editorArea{height:auto;flex:1 1 auto;min-height:110px}
#app.editor-full .input-pane,#app.editor-full .preview-pane{flex:1 1 50%;height:auto;display:block;overflow:auto}



/* 颜色选择器 */
.colorPicker{
  position:fixed;z-index:80;background:var(--bg);border:2px solid var(--line);
  padding:10px;box-shadow:0 10px 30px rgba(0,0,0,.25);display:flex;flex-direction:column;gap:8px;
}
.colorPicker[hidden]{display:none}
.colorPicker[open]{display:flex}
.colorPickerHead{display:flex;align-items:center;justify-content:space-between;font-size:12px;color:var(--muted)}
.colorPickerHead button{border:none;background:none;cursor:pointer;color:var(--muted);font-size:14px}
.colorPicker input[type=color]{width:100%;height:40px;border:1px solid var(--line);border-radius:0;padding:0;background:var(--bg);cursor:pointer}
.colorPresets{display:flex;flex-wrap:wrap;gap:4px}
.colorPresets button{width:22px;height:22px;border:1px solid var(--line-soft);border-radius:0;cursor:pointer;padding:0}
.colorPickerFoot{display:flex;gap:8px;justify-content:flex-end}
.colorPickerFoot button{
  border:1px solid var(--line);border-radius:0;background:var(--bg);padding:6px 14px;cursor:pointer;font-size:12px;
}
.colorPickerFoot button:first-child{background:var(--ink);color:#fff}

@media (max-width:640px){
  .editorArea{flex-direction:column;height:auto}
  .editorArea .pane{flex:none;height:200px;width:100%}
  .preview-pane{border-left:none;border-top:1px solid var(--line-soft)}
}

.composerFoot{
  display:flex;align-items:flex-end;justify-content:space-between;gap:12px;
  /* 底部安全区由 #composer 统一处理，这里仅常规间距 + 左右安全边距 */
  padding-top:8px;
  padding-bottom:10px;
  padding-left:max(12px, env(safe-area-inset-left, 0px));
  padding-right:max(12px, env(safe-area-inset-right, 0px));
  border-top:1px solid var(--line-soft);
}
.composerFoot .status{color:var(--muted);font-size:12px}
#sendBtn{
  border:2px solid var(--line);border-radius:0;background:var(--ink);color:#fff;
  padding:9px 26px;cursor:pointer;font-size:14px;letter-spacing:.18em;
}
#sendBtn:disabled{opacity:.4;cursor:not-allowed}

/* 提示卡片 */
.modal{
  position:fixed;inset:0;z-index:60;display:none;align-items:center;justify-content:center;
  background:rgba(0,0,0,.5);
}
.modal.on{display:flex}
.modal .card{
  width:min(320px,90%);padding:22px;border:2px solid var(--line);border-radius:0;
  background:var(--bg);text-align:center;
}
.modal h3{margin:0 0 10px;font-size:16px}
.modal p{font-size:13px;color:#333;margin:0 0 16px;line-height:1.7;word-break:break-all}
.modal .row{display:flex;gap:8px;justify-content:center}
.modal button{
  border:1px solid var(--line);border-radius:0;background:var(--bg);
  padding:7px 18px;cursor:pointer;font-size:13px;min-width:74px;
}
.modal button:first-child{background:var(--ink);color:#fff}
.modal button:disabled{opacity:.4;cursor:not-allowed}
#uploadModal .card{width:min(380px,90%)}

/* 设置面板 */
.top .room{cursor:pointer}
#settingsModal .card{width:min(320px,90%);text-align:left}
.setRow{display:flex;align-items:center;justify-content:space-between;gap:10px;margin:0 0 14px}
.setLabel{font-size:14px;color:var(--ink)}
.setValue{font-size:13px;color:var(--muted);white-space:nowrap;font-variant-numeric:tabular-nums}
.setAction{
  border:1px solid var(--line);border-radius:0;background:var(--bg);color:var(--ink);
  padding:6px 14px;cursor:pointer;font-size:13px;
}
.setAction:disabled{opacity:.45;cursor:not-allowed}
.setInput{
  width:88px;padding:5px 8px;border:1px solid var(--line);border-radius:0;
  background:var(--bg);color:var(--ink);font-size:13px;outline:none;
}
.setInput:focus{border-color:var(--ink)}
.setInput::-webkit-inner-spin-button{opacity:.4}
/* 通知开关：与授权按钮（.setAction）一致的平凡按钮，点一下切换开/关，无动画 */
.toggle{
  border:1px solid var(--line);border-radius:0;background:var(--bg);color:var(--ink);
  padding:6px 14px;cursor:pointer;font-size:13px;
}
.toggle.on{background:var(--ink);color:#fff;border-color:var(--ink)}
.toggle:disabled{opacity:.45;cursor:not-allowed}
.modalStatus{min-height:18px;font-size:12px;color:var(--muted);margin:0 0 14px;word-break:break-all}

/* ---------- 字体子面板 ---------- */
#fontModal .card{width:min(340px,92%);text-align:left}
.fontSection{margin:0 0 16px}
.fontSectionTitle{font-size:12px;color:var(--muted);margin:0 0 6px}
.fontCurrent{
  font-size:14px;color:var(--ink);border:1px solid var(--line);padding:7px 10px;
  margin:0 0 10px;border-radius:0;overflow-wrap:anywhere;
}
.fontList{display:flex;flex-direction:column;gap:6px;max-height:150px;overflow:auto}
.fontItem{
  display:flex;align-items:center;justify-content:space-between;gap:8px;
  border:1px solid var(--line-soft);padding:6px 8px;border-radius:0;
}
.fontItem .fiName{font-size:13px;color:var(--ink);overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.fontItem .fiMeta{font-size:11px;color:var(--muted);white-space:nowrap}
.fontItem .fiActs{display:flex;gap:6px}
.fontEmpty{font-size:12px;color:var(--muted)}
.fontItem button{min-width:0;padding:3px 8px;font-size:12px;border:1px solid var(--line);border-radius:0;background:var(--bg);color:var(--ink);cursor:pointer}
.fontItem button:disabled{opacity:.5;cursor:not-allowed}
.fontProgress{position:relative;display:none;height:28px;margin:8px 0 0;background:var(--bg2);border:1px solid var(--line-soft);border-radius:0;overflow:hidden}
.fontProgress.on{display:block}
.fontProgress > i{position:absolute;left:0;top:0;bottom:0;width:0;background:var(--ink);transition:width .15s linear}
.fontProgress > span{position:relative;z-index:2;display:flex;align-items:center;justify-content:center;height:100%;font-size:12px;color:var(--muted)}
#dropZone{
  border:2px dashed var(--line-soft);background:var(--bg2);color:var(--muted);
  padding:24px 12px;font-size:13px;cursor:pointer;border-radius:0;
  transition:border-color .15s ease,background-color .15s ease;
}
#dropZone.drag{border-color:var(--line);background:#fff;color:var(--ink)}
.progressTrack{height:12px;border:1px solid var(--line);border-radius:0;background:var(--bg2);overflow:hidden}
.progressBar{height:100%;width:0%;background:var(--ink);transition:width .2s ease}
/* 悬停样式仅在真正支持悬停的设备上生效（鼠标/触控板）；
   手机/平板等触屏不会在“点按”时触发悬停态、也不会残留 */
@media (hover: hover) and (pointer: fine){
  #login button:hover{background:#fff;color:#000}
  .top .searchResults .sr:hover{background:var(--bg2);color:var(--ink)}
  .top button:hover{background:#000;color:#fff}
  .msg .meta .copySrc:hover{color:var(--ink)}
  .msg .meta .quoteSrc:hover{color:var(--ink)}
  .newMsg:hover{background:var(--ink);color:#fff}
  .toolbar button:hover{border-color:var(--line);background:#000;color:#fff}
  .colorPresets button:hover{border-color:var(--line)}
  .colorPickerFoot button:first-child:hover{background:#fff;color:#000}
  #sendBtn:hover{background:#fff;color:#000}
  .modal button:first-child:hover{background:#fff;color:#000}
  .modal button:hover{border-color:var(--line)}
  .top .room:hover{opacity:.7}
  .setAction:hover{border-color:var(--ink)}
  .toggle:hover{border-color:var(--ink)}
  .toggle.on:hover{background:#fff;color:#000}
}
/* 触屏设备：手指按在元素上时给出同样的“悬停”反馈（按下出现、抬起消失） */
@media (hover: none), (pointer: coarse){
  #login button:active{background:#fff;color:#000}
  .top .searchResults .sr:active{background:var(--bg2);color:var(--ink)}
  .top button:active{background:#000;color:#fff}
  .msg .meta .copySrc:active{color:var(--ink)}
  .msg .meta .quoteSrc:active{color:var(--ink)}
  .newMsg:active{background:var(--ink);color:#fff}
  .toolbar button:active{border-color:var(--line);background:#000;color:#fff}
  .colorPresets button:active{border-color:var(--line)}
  .colorPickerFoot button:first-child:active{background:#fff;color:#000}
  #sendBtn:active{background:#fff;color:#000}
  .modal button:first-child:active{background:#fff;color:#000}
  .modal button:active{border-color:var(--line)}
  .top .room:active{opacity:.7}
  .setAction:active{border-color:var(--ink)}
  .toggle:active{border-color:var(--ink)}
  .toggle.on:active{background:#fff;color:#000}
}

/* ================= 黑夜模式 =================
   body.dark 时整体反色：白变黑、黑变白。核心换 CSS 变量，
   其余写死的极简粗细色/链接蓝等逐一覆盖。 */
body.dark{
  --bg:#121212;            /* 原 #fff */
  --bg2:#1e1e1e;           /* 原 #f4f4f4 */
  --ink:#eeeeee;           /* 原 #111 */
  --muted:#969696;         /* 原 #8a8a8a */
  --line:#cfcfcf;          /* 原 #222 */
  --line-soft:#3a3a3a;     /* 原 #d8d8d8 */
}
body.dark #login button{background:#000;color:#fff}
body.dark .colorPickerFoot button:first-child{background:#000;color:#fff}
body.dark #sendBtn{background:#000;color:#fff}
body.dark .modal button:first-child{background:#000;color:#fff}
body.dark .toggle.on{background:#000;color:#fff}
body.dark .rich blockquote{color:#c7c7c7}
body.dark .modal p{color:#c7c7c7}
body.dark .rich pre{background:#1b1b1b}
body.dark .rich .codeblock{background:#1b1b1b;border-color:var(--line-soft)}
body.dark .rich .codeblock-bar{background:#161616;border-color:var(--line-soft)}
body.dark .rich .codeblock.collapsed pre::after{
  background:linear-gradient(180deg, rgba(27,27,27,0), rgba(27,27,27,.72));
}
body.dark .rich a,body.dark .msg.mine .rich a,body.dark .rich a.uclink,body.dark .rich .msgquote{color:#71a8ff}
body.dark #chat .msg.highlight{background:rgba(113,168,255,.16);outline-color:#71a8ff}
body.dark #dropZone.drag{background:#000;color:#fff}
/* 阴影在黑夜下更沉稳 */
body.dark .top .searchResults{box-shadow:0 8px 24px rgba(0,0,0,.55)}
body.dark .top .searchResults .sr mark{background:rgba(255,213,0,.5);color:#111}
body.dark .colorPicker{box-shadow:0 10px 30px rgba(0,0,0,.6)}

/* 鼠标悬停态：黑夜下把浅底/深字的 hover 反色，深底/浅字的也反色 */
@media (hover: hover) and (pointer: fine){
  body.dark #login button:hover,body.dark .colorPickerFoot button:first-child:hover,
  body.dark #sendBtn:hover,body.dark .modal button:first-child:hover,body.dark .toggle.on:hover{background:#fff;color:#000}
  body.dark .top button:hover,body.dark .toolbar button:hover{background:#fff;color:#000}
  body.dark .newMsg:hover{background:#000;color:#fff}
}
/* 触屏按下态：同理反色 */
@media (hover: none), (pointer: coarse){
  body.dark #login button:active,body.dark .colorPickerFoot button:first-child:active,
  body.dark #sendBtn:active,body.dark .modal button:first-child:active,body.dark .toggle.on:active{background:#fff;color:#000}
  body.dark .top button:active,body.dark .toolbar button:active{background:#fff;color:#000}
  body.dark .newMsg:active{background:#000;color:#fff}
}
</style>
</head>
<body>

<!-- 登录 -->
<div id="login">
  <div class="brand">
    <h1>欣欣聊天室</h1>
    <p>StellaFortuna-Chat</p>
  </div>
  <form id="loginForm">
    <input type="password" id="pass" placeholder="输入访问密码" autocomplete="current-password">
    <button type="submit">进 入 聊 天 室</button>
    <div class="err" id="loginErr"></div>
  </form>
  <div class="hint">不同密码对应不同账户身份</div>
</div>

<!-- Safari 临时屏蔽页（与登录页同风格，无输入框） -->
<div id="safariBlock">
  <div class="brand">
    <h1>欣欣聊天室</h1>
    <p>StellaFortuna-Chat</p>
  </div>
  <div class="msg">由于safari的某些未知奇妙特性，在safari上暂时不能工作</div>
  <div class="hint">请使用 Chrome 或其他浏览器访问</div>
</div>
<script>
/* WebKit 引擎特征检测（不解析 UA）：Safari 及 iOS 各种内嵌 WebView 应用（微信/QQ 等，UA 各不相同）
   都暴露 GestureEvent，且 navigator.vendor 为 "Apple Computer, Inc."；
   Chrome/Edge/Firefox 及安卓浏览器均无这两个特征，不会误伤。
   立即执行：在解析到该处时 DOM 元素已存在，直接切换，避免闪现登录页。 */
(function(){
  var isWebKit = false;
  try { isWebKit = ('GestureEvent' in window); } catch(e){}
  if (!isWebKit){
    try {
      isWebKit = (navigator.vendor || '').toLowerCase().indexOf('apple computer') === 0;
    } catch(e){}
  }
  if (isWebKit){
    var block = document.getElementById('safariBlock');
    var login = document.getElementById('login');
    if (block) block.classList.add('show');
    if (login) login.style.display = 'none';
    window.__safariBlocked = true;
  }
})();
</script>

<!-- 主界面 -->
<div id="app">
  <div class="top">
    <div class="room" id="roomTitle" onclick="openSettings()" title="设置">欣欣聊天室</div>
    <div class="topSearch" id="topSearch">
      <input type="search" id="searchInput" placeholder="搜索消息…" autocomplete="off">
      <div class="searchResults" id="searchResults" hidden></div>
    </div>
    <div class="who" id="whoTag"></div>
    <button id="toBottomBtn">回到最下</button>
    <button id="editorToggleBtn">写消息</button>
  </div>
  <div id="chat" tabindex="0"></div>
  <div id="composer">
    <div class="toolbar" id="toolbar"></div>
    <div class="editorArea" id="editorArea">
      <div class="pane input-pane">
        <textarea id="editor" placeholder="输入消息…（支持 Markdown / 猎户座富文本格式）"></textarea>
      </div>
      <div class="pane preview-pane" id="previewPane">
        <div class="rich preview" id="previewOut"></div>
      </div>
    </div>
    <div class="composerFoot">
      <div class="status" id="statusText"></div>
      <div style="display:flex;gap:8px">
        <button id="sendBtn">发 送</button>
      </div>
    </div>
  </div>
</div>

<!-- 颜色选择器弹层 -->
<div class="colorPicker" id="colorPicker" hidden>
  <div class="colorPickerHead"><span>选择文字颜色</span><button id="colorPickerClose" type="button">✕</button></div>
  <input type="color" id="colorValue" value="#000000">
  <div class="colorPresets" id="colorPresets"></div>
  <div class="colorPickerFoot">
    <button id="colorPickerCancel" type="button">取消</button>
    <button id="colorPickerOk" type="button">确 定</button>
  </div>
</div>

<!-- 文件上传弹窗 -->
<div class="modal" id="uploadModal">
  <div class="card">
    <h3 id="uploadTitle">上传文件</h3>
    <p style="font-size:13px;color:var(--muted)">拖拽文件到下方，或点击选择（≤ 10MB）</p>
    <div id="dropZone">点击选择 / 拖拽文件到这里</div>
    <div id="uploadProgress" style="display:none;margin-top:10px">
      <div class="progressTrack"><div class="progressBar" id="progressBar"></div></div>
      <div id="progressText" style="font-size:12px;color:var(--muted);margin-top:4px">正在上传…</div>
    </div>
    <div id="uploadResult" style="display:none;margin-top:10px;overflow-wrap:anywhere">
      <div style="font-size:12px;color:var(--muted);margin-bottom:4px">上传完成，地址：</div>
      <code id="uploadUrl" style="font-size:11px"></code>
    </div>
    <div class="row" style="margin-top:12px">
      <button id="uploadDone" disabled>完成</button>
      <button id="uploadCancel">取消</button>
    </div>
  </div>
</div>
<input type="file" id="fileInput" hidden>

<!-- 通用确认弹窗（删除/退出/加载提示） -->
<div class="modal" id="modal">
  <div class="card">
    <h3 id="modalTitle"></h3>
    <p id="modalBody"></p>
    <div class="row">
      <button id="modalOk">确定</button>
      <button id="modalCancel">取消</button>
    </div>
  </div>
</div>

<!-- 设置面板（点击顶栏“欣欣聊天室”标题打开） -->
<div class="modal" id="settingsModal">
  <div class="card">
    <h3>设置</h3>
    <div class="setRow">
      <span class="setLabel">授权通知</span>
      <button class="setAction" id="notifGrant" onclick="requestNotification()">请求授权</button>
    </div>
    <div class="setRow">
      <span class="setLabel">通知开关</span>
      <button class="toggle" id="notifSwitch" onclick="toggleNotifSwitch()" aria-pressed="false" disabled>关</button>
    </div>
    <div class="modalStatus" id="notifStatus"></div>
    <div class="setRow">
      <span class="setLabel">黑夜模式</span>
      <button class="toggle" id="darkSwitch" onclick="toggleDark()" aria-pressed="false">开</button>
    </div>
    <div class="setRow">
      <span class="setLabel">导出聊天记录</span>
      <button class="setAction" id="exportBtn" onclick="exportChat()">导出</button>
    </div>
    <div class="setRow">
      <span class="setLabel">最小 id</span>
      <input type="number" id="exportMin" class="setInput" min="1" placeholder="留空=最早">
      <span class="setLabel">最大 id</span>
      <input type="number" id="exportMax" class="setInput" min="1" placeholder="留空=最新">
    </div>
    <div class="modalStatus" id="exportStatus"></div>
    <div class="setRow">
      <span class="setLabel">缓存大小</span>
      <span class="setValue" id="cacheSize">—</span>
      <button class="setAction" id="clearCacheBtn" onclick="clearMessageCache()">清除缓存</button>
    </div>
    <div class="setRow">
      <span class="setLabel">字体</span>
      <button class="setAction" id="fontPanelBtn" onclick="openFontPanel()">管理</button>
    </div>
    <div class="setRow">
      <span class="setLabel">退出登录</span>
      <button class="setAction" onclick="doLogout()">退出</button>
    </div>
    <div class="row">
      <button onclick="closeSettings()">完成</button>
    </div>
  </div>
</div>

<!-- 字体子面板 -->
<div class="modal" id="fontModal">
  <div class="card">
    <h3>字体</h3>

    <div class="fontSection">
      <div class="fontSectionTitle">当前使用</div>
      <div class="fontCurrent" id="fontCurrent">默认</div>
      <div style="display:flex;gap:8px">
        <button class="setAction" id="fontExportBtn" onclick="exportSlotFont()" disabled>导出槽位字体</button>
        <button class="setAction" id="fontClearBtn" onclick="clearFontSlot()" disabled>清除以恢复默认</button>
      </div>
    </div>

    <div class="fontSection">
      <div class="fontSectionTitle">从本地选择</div>
      <div style="display:flex;gap:8px;align-items:center">
        <button class="setAction" id="fontLocalPickBtn" onclick="pickLocalFont()">选择字体文件</button>
        <span class="fiMeta" id="fontLocalPickName" style="font-size:12px;color:var(--muted)">未选择</span>
      </div>
      <input type="file" id="fontLocalInput" accept=".ttf,.otf,.woff,.woff2,font/ttf,font/otf,font/woff,font/woff2" style="display:none">
    </div>

    <div class="fontSection">
      <div class="fontSectionTitle">从服务器下载</div>
      <div class="fontList" id="fontServerList"><span class="fontEmpty">加载中…</span></div>
      <div class="fontProgress" id="fontProgress">
        <i></i>
        <span id="fontProgressText">下载中…</span>
      </div>
    </div>

    <div class="modalStatus" id="fontStatus"></div>
    <div class="row">
      <button onclick="closeFontPanel()">完成</button>
    </div>
  </div>
</div>

<script src="lib/markdown-it.min.js"></script>
<script src="lib/markdown-it-footnote.min.js"></script>
<script src="lib/purify.min.js"></script>
<script src="lib/highlight.min.js"></script>

<script>
/* ================================================================
 *  欣欣聊天室 前端逻辑
 *  - 登录凭证写到 get 参数 p 并保存到 sessionStorage
 *  - 每次 fetch 都带上 ?p= 凭证
 *  - JS 轮询新消息；消息分片加载；滚动到底自动拉新 / 留顶保持位置
 *  - 猎户座编辑器格式渲染
 * ================================================================ */
'use strict';

/* ---------- 全局状态 ---------- */
let CHUNK = 12;            // 每片消息条数（自适应：见 computeChunk）
const POLL_MS = 500;        // 轮询间隔（0.5 秒）
let CODE = null;            // 凭证（get 参数 p）
let ME = null;              // {name, canSend}

/* 跳表：按消息 id 有序。底层为双向链表（node.prev/next），使“向左/向右数 N 条”靠指针
   行走 O(N)（即 O(1)*N）；上层跳过索引使按 id 查找 O(log n)。删除/插入稳定。
   节点: { id, prev, next, nextLv[], msg } */
function makeSkipList(){
  const P = 0.5, MAXLV = 16;
  const head = { id: -Infinity, prev: null, next: null, nextLv: [], msg: null };
  const tail = { id: Infinity,  prev: null, next: null, nextLv: [], msg: null };
  head.next = tail; tail.prev = head;
  let size = 0;
  function rndLevel(){ let l = 0; while (l < MAXLV && Math.random() < P) l++; return l; }
  // 返回小于 id 的最大节点（走跳表）
  function lowerBound(id){
    let x = head, lv = MAXLV;
    while (lv >= 0){
      while (x.nextLv[lv] && x.nextLv[lv].id < id) x = x.nextLv[lv];
      lv--;
    }
    return x;   // x.next 是 >= id 的第一个
  }
  function findNode(id){
    let x = lowerBound(id);
    return (x.next && x.next.id === id) ? x.next : null;
  }
  return {
    get size(){ return size; },
    get head(){ return head; },
    get tail(){ return tail; },
    has(id){ return !!findNode(id); },
    node(id){ return findNode(id); },
    /* 最大的 id < 某值 的节点（不含等于） */
    predBefore(id){
      let x = lowerBound(id);
      return (x === head) ? null : x;
    },
    /* 最小的 id > 某值 的节点（不含等于） */
    succAfter(id){
      let x = lowerBound(id);            // < id 最大值
      let s = x.next;                    // >= id 第一个
      if (s !== tail && s.id === id) s = s.next;   // 跳过等于
      return (s === tail) ? null : s;
    },
    minNode(){ return head.next === tail ? null : head.next; },
    maxNode(){ return tail.prev === head ? null : tail.prev; },
    minId(){ const n = head.next === tail ? null : head.next; return n ? n.id : null; },
    maxId(){ const n = tail.prev === head ? null : tail.prev; return n ? n.id : null; },
    add(msg){
      const id = Number(msg.id);
      if (findNode(id)) return;               // 已有则忽略（幂等）
      const node = { id: id, msg: msg, prev: null, next: null, nextLv: [] };
      // 找插入位置
      let preds = new Array(MAXLV + 1).fill(head);
      let x = head;
      for (let lv = MAXLV; lv >= 0; lv--){
        while (x.nextLv[lv] && x.nextLv[lv].id < id) x = x.nextLv[lv];
        preds[lv] = x;
      }
      const succ = x.next;                    // 底层后继
      // 底部链
      node.next = succ; node.prev = x;
      x.next = node; succ.prev = node;
      // 上层链
      const lvl = rndLevel();
      for (let lv = 0; lv <= lvl; lv++){
        const p = preds[lv];
        node.nextLv[lv] = p.nextLv[lv] || null;
        p.nextLv[lv] = node;
      }
      size++;
      return node;
    },
    remove(id){
      const node = findNode(id);
      if (!node) return false;
      // 取下所有层
      let x = head;
      for (let lv = MAXLV; lv >= 0; lv--){
        while (x.nextLv[lv] && x.nextLv[lv].id < id) x = x.nextLv[lv];
        if (x.nextLv[lv] && x.nextLv[lv].id === id) x.nextLv[lv] = x.nextLv[lv].nextLv[lv] || null;
      }
      // 底层
      node.prev.next = node.next;
      node.next.prev = node.prev;
      size--;
      return true;
    },
    /* 从“id 严格大于 base”的第一个节点向右走 count 步，返回节点的 msg 数组 */
    greaterAfter(base, count){
      let x = lowerBound(base + 1);
      if (x.next === tail) return [];
      x = x.next;
      const out = [];
      while (out.length < count && x !== tail){ out.push(x.msg); x = x.next; }
      return out;
    },
    /* 从“id 严格小于 base”的最末节点向左走 count 步，返回 msg 数组（升序） */
    lessBefore(base, count){
      let x = lowerBound(base);   // 返回 < base 的最大节点
      const out = [];
      while (out.length < count && x !== head){ out.unshift(x.msg); x = x.prev; }
      return out;
    },
    /* 尽量向左数 count 条现存（遇断即停，但链上是连续无断的，即数到底），返回升序数组 */
    walkLeft(fromNode, count){
      const out = [];
      let x = fromNode ? fromNode.prev : tail.prev;
      while (out.length < count && x !== head){ out.unshift(x.msg); x = x.prev; }
      return out;
    },
    walkRight(fromNode, count){
      const out = [];
      let x = fromNode ? fromNode.next : head.next;
      while (out.length < count && x !== tail){ out.push(x.msg); x = x.next; }
      return out;
    },
    /* 等价 MSGS.filter(fn) 返回数组 */
    filterToArray(fn){
      const out = []; let x = head.next;
      while (x !== tail){ const m = x.msg; if (fn(m)) out.push(m); x = x.next; }
      return out;
    },
    /* 等价 MSGS.some(fn) */
    some(fn){
      let x = head.next;
      while (x !== tail){ if (fn(x.msg)) return true; x = x.next; }
      return false;
    },
    /* 遍历（按 id 序） */
    forEach(fn){
      let x = head.next;
      while (x !== tail){ fn(x.msg); x = x.next; }
    },
    toArray(){
      const out = []; let x = head.next;
      while (x !== tail){ out.push(x.msg); x = x.next; }
      return out;
    },
    /* —— 数组兼容辅助（跳表底层即有序，天然等效） —— */
    get length(){ return size; },
    push(m){ this.add(m); return size; },
    find(fn){
      let x = head.next;
      while (x !== tail){ if (fn(x.msg)) return x.msg; x = x.next; }
      return undefined;
    },
    findIndex(fn){
      let x = head.next, i = 0;
      while (x !== tail){ if (fn(x.msg)) return i; x = x.next; i++; }
      return -1;
    },
    // 按【插入后的 id 顺序】取第 i 条（供 MSGS[lastI] 用法）
    getByIndex(i){
      if (i < 0) return undefined;
      let x = head.next, k = 0;
      while (x !== tail){ if (k === i) return x.msg; x = x.next; k++; }
      return undefined;
    },
    reduce(fn, init){
      let acc = init; let x = head.next;
      while (x !== tail){ acc = fn(acc, x.msg); x = x.next; }
      return acc;
    },
    filter(fn){ return this.filterToArray(fn); },
    splice(i, n){
      // 删除“按 id 序第 i 条起 n 条”
      let x = head.next, k = 0;
      while (x !== tail && k < i){ x = x.next; k++; }
      let removed = 0;
      while (x !== tail && removed < n){
        const nx = x.next;
        this.remove(x.id);
        x = nx; removed++;
      }
      return removed;
    }
  };
}
let MSGS = makeSkipList();    // 已加载的消息缓存（跳表，按 id 有序）
let totalCount = 0;         // 服务器端总消息数
let latestId = 0;           // 全局最大消息 id（永不减少，任何获取到即时更新），新消息轮询锚点
let globalEarliestId = 0;   // 服务端全局最小现存消息 id（一进入即获取；其被删则相应更新）
let topSeq = Infinity;      // 已加载最旧一条的 seq（向上翻片游标）
let bottomSeq = -1;         // 已加载最新一条的 seq（向下拉新游标）
let atBottom = true;        // 是否贴底（距底 <=5px）
let pollTimer = null;       // 新消息轮询定时器
let viewportTimer = null;   // 视口分片刷新定时器
let busy = false;           // 正在拉取/发送

/* 自适应片大小：CHUNK = 四屏幕的“最矮消息”数量（4 × 视口高 ÷ 最矮消息高）。
   用一个最小消息渲染到隐藏容器量其高度作为“最矮消息”的近似。 */
function computeChunk(){
  try {
    var measurer = document.createElement('div');
    measurer.className = 'msg other';
    measurer.style.cssText = 'position:absolute;visibility:hidden;left:-9999px;width:' + (chat?chat.clientWidth:400) + 'px;';
    var c = document.createElement('div'); c.className='content';
    var b = document.createElement('div'); b.className='bubble';
    var r = document.createElement('div'); r.className='rich';
    r.innerHTML = '<p>x</p>';
    b.appendChild(r); c.appendChild(b); measurer.appendChild(c);
    document.body.appendChild(measurer);
    var h = measurer.offsetHeight || 20;
    document.body.removeChild(measurer);
    var vh = window.innerHeight || 600;
    var n = Math.max(1, Math.floor(4 * vh / h));
    CHUNK = n;
  } catch(e){ CHUNK = 12; }
}
let busyOlder = false;      // 正在向上翻片（独立，避免被 send/下翻阻塞导致“加载中却不发请求”）
let busyNewer = false;      // 正在向下翻片（独立）

/* ---------- 猎户座编辑器渲染 ---------- */
const md = window.markdownit({
  // html:true 以便将猎户座 BBCode 预处理的 HTML(span/details/spoiler/u) 原样输出，
  // 之后再经 DOMPurify 白名单清洗，兼顾富文本与安全。
  html: true, linkify: true, breaks: true,
  highlight: function(str, lang){
    // 只返回高亮后的代码（不带 <pre><code> 包裹）；外层结构由自定义 fence 规则生成
    if (lang && window.hljs && hljs.getLanguage(lang)) {
      try { return hljs.highlight(str, {language: lang, ignoreIllegal: true}).value; }
      catch(e){ return md.utils.escapeHtml(str); }
    }
    return md.utils.escapeHtml(str);
  }
});
md.use(window.markdownitFootnote);
// 自定义脚注块渲染：不用 <hr>+<section>，改用 <div class="footnotes">，
// 规避 DOMPurify 对“void 元素 / section 紧跟特定块元素”的解析缺陷
md.renderer.rules.footnote_block_open = () => '<div class="footnotes">';
md.renderer.rules.footnote_block_close = () => '</div>';
// 自定义围栏（代码块）渲染：生成“语言标签 + 复制 + 展开/收起”的外层结构，
// 避免 markdown-it 默认 <pre><code> 再包一层导致多层嵌套。
md.renderer.rules.fence = function(tokens, idx, options, env, self){
  const tok = tokens[idx];
  const lang = (tok.info || '').trim().split(/\s+/)[0] || '';
  const code = options.highlight ? options.highlight(tok.content, lang) : md.utils.escapeHtml(tok.content);
  const langLabel = lang || 'code';
  // 短代码块（≤14 行）不需要展开：不加 collapsed，也不显示展开按钮
  const lineCount = tok.content.replace(/\n$/, '').split('\n').length;
  const short = lineCount <= 14;
  const cls = short ? 'codeblock' : 'codeblock collapsed';
  const toggleBtn = short ? '' :
    '<button type="button" class="codeblock-toggle" title="展开/收起"></button>';
  return '<div class="' + cls + '" data-lang="' + md.utils.escapeHtml(langLabel) + '">'
    + '<div class="codeblock-bar">'
    + '<span class="codeblock-lang">' + md.utils.escapeHtml(langLabel) + '</span>'
    + '<button type="button" class="codeblock-copy" title="复制代码">复制</button>'
    + toggleBtn
    + '</div>'
    + '<pre><code class="hljs">' + code + '</code></pre>'
    + '</div>';
};
// 所有链接（[]() 与 linkify 的自动链接）都在新标签页打开
md.renderer.rules.link_open = function(tokens, idx, options, env, self){
  tokens[idx].attrSet('target', '_blank');
  tokens[idx].attrSet('rel', 'noopener');
  return self.renderToken(tokens, idx, options);
};
/* 图片倍率语法：链接末尾的 #e = 0.5 倍，#e1.7 = 1.7 倍。
   该片段对图片加载无意义，从 src 剥掉，记到 data-scale，等加载完成后由 applyImgScale 缩放。 */
var SCALE_RE = /#e(\d+(?:\.\d+)?)?$/i;
function splitScale(url){
  var m = SCALE_RE.exec(String(url || ''));
  if (!m) return { url: String(url || ''), scale: null };
  return { url: url.slice(0, m.index), scale: m[1] || '0.5' };
}
var defaultImageRule = md.renderer.rules.image;
md.renderer.rules.image = function(tokens, idx, options, env, self){
  var tok = tokens[idx];
  var si = tok.attrIndex('src');
  if (si >= 0){
    var sp = splitScale(tok.attrs[si][1]);
    if (sp.scale){
      tok.attrs[si][1] = sp.url;
      tok.attrPush(['data-scale', sp.scale]);
    }
  }
  return defaultImageRule(tokens, idx, options, env, self);
};

/* 复制猎户座编辑器 BBCode 扩展（剧透 / 折叠 / 下划线 / 彩色文本 / 脚注 / 滚动盒） */
/* 消除 markdown 的“多种奇怪解析路径”：
   分隔线仅保留“三个连字符 ---”这一种；三颗星、三个下划线等不再触发分隔线；
   行首的星号或加号（后跟空白）不再触发无序列表，按字面渲染。 */
function normalizeAmbiguities(text){
  return String(text || '').split('\n').map(function(line){
    // 1) 纯星号或纯下划线的“分隔线”行 -> 转义首字符，按字面显示（保留 ---）
    if (/^[ \t]*[_*](?:[ \t]*[_*]){2,}[ \t]*$/.test(line)){
      return line.replace(/^([ \t]*)([*_])/, function(m,a,b){ return a+'\\'+b; });
    }
    // 2) 行首 * / + 列表标记（* 后跟空白）-> 转义，不当作列表。
    //    注意：不能含“|$ 后跟行尾”，否则会把“单独一行的 *”（多行斜体的开启符）也转义掉，
    //    导致 *你好…你好* 这种跨行斜体失效、留下字面星号。
    if (/^[ \t]*[*+](?=[ \t])/.test(line)){
      return line.replace(/^([ \t]*)([*+])/, function(m,a,b){ return a+'\\'+b; });
    }
    return line;
  }).join('\n');
}

/* 猎户座编辑器 BBCode（u/color/spoiler/details/quote）递归渲染：
   用带嵌套匹配的解析器把每个标签包裹的内容【先当 markdown 解析】再套标签 HTML，
   从根本上避免“标记位于行首时被 markdown-it 当作原生 HTML 块、不再解析内层 markdown”
   导致的嵌套失效（例如 [spoiler] 里放图片/标题/列表/代码不渲染）。 */

// 找到 openAt 处 [tag...] 的配对的 [/tag] 结束位置（含），考虑同标签嵌套
function bbcFindClose(src, openAt, tag, openTagLen){
  var openRe = new RegExp('\\[' + tag + '(?==|])', 'g');
  var closeRe = new RegExp('\\[/' + tag + '\\]', 'g');
  openRe.lastIndex = closeRe.lastIndex = openAt + openTagLen;
  var depth = 1, scan = openAt + openTagLen;
  while (scan < src.length){
    openRe.lastIndex = scan; var o = openRe.exec(src);
    closeRe.lastIndex = scan; var c = closeRe.exec(src);
    if (o && (!c || o.index < c.index)){ depth++; scan = o.index + o[0].length; }
    else if (c){ depth--; if (depth === 0) return c.index + c[0].length; scan = c.index + c[0].length; }
    else return -1;
  }
  return -1;
}
// 粗略判断内容是否需要按“块级”渲染（多行 / 段落 / 标题 / 列表 / 引用 / 代码 / 行首图片）
function bbcIsBlockish(body){
  if (/\n/.test(body)) return true;
  if (/^\s{0,3}(#{1,6}\s|[-*+]\s|\d+\.\s|>\s|```|~~~)/m.test(body)) return true;
  if (/^\s{0,3}\!\s*\[/m.test(body)) return true;
  if (/^\s{0,3}\[(details|spoiler|u|color)\b/.test(body)) return true;
  return false;
}
function bbcUnquote(s){
  if (s == null) return '';
  s = String(s).trim();
  if (s.length >= 2 && s.charAt(0) === '"') s = s.slice(1);
  if (s.length >= 1 && s.charAt(s.length - 1) === '"') s = s.slice(0, -1);
  return s;
}
/* 行内 BBCode（u/color/行内 spoiler/quote/未知标签）转换后用“占位标记”交给 markdown：
   因为 markdown 的强调（双星号粗体、单星号斜体，以及行首图片）无法跨越它所见的内联 HTML
   端点，若直接把 [color] 展开成 <span> HTML 再交给 md.render，像 123**粗体包BBCode** 这类
   “粗体包裹 BBCode”会失效、留下字面星号。解决办法：先把行内标签整体替换成一个非标点控制符
   占位（对 markdown 而言是透明字符，不影响强调的左右夹配），等 md 渲染完再回填成真正 HTML。 */
var __convRep = [];   // sentinel id -> 该行内标签渲染出的 HTML
function mdRender(text){ return unwrapSentinels(md.render(text)); }
function mdRenderInline(text){ return unwrapSentinels(md.renderInline(text)); }
function unwrapSentinels(html){
  html = String(html);
  var guard = 0;
  while (guard++ < 2000){
    var m = /\u0001([0-9]+)\u0002/.exec(html);
    if (!m) break;
    var id = +m[1];
    var v = (__convRep[id] != null) ? __convRep[id] : m[0];
    html = html.split(m[0]).join(v);
  }
  return html;
}
function storeSentinel(html){
  var id = __convRep.length; __convRep.push(html);
  return '\u0001' + id + '\u0002';
}

/* 把裸写的 uploads/… 路径换成“占位标记”（与行内 BBCode 同机制）。
   - 在非 URL 字符（中文、空格等）处断开路径；
   - 已是 markdown 图片/链接的 ![alt](uploads/…) 或 [txt](uploads/…) 由 markdown 自己渲染，不加链接；
   - 反斜杠形式 \uploads/… 去掉反斜杠、保留纯文本，不链接。
   用占位而非直接 <a>：这样粗体/斜体包裹 uploads 时，强调能像跨 BBCode 那样跨过占位而正常配对；
   之后再在这外层把占位还原成 <a> 链接。 */
function linkifyUploads(text){
  var s = String(text || '');
  var U = "[A-Za-z0-9\\-_.~:/%?#&+@!$=]";
  // 0) 禁止解析区（代码块围栏 + 行内码）内容整段收进占位，不做 uploads 链接化；末尾还原
  var fenceGuarded = [];
  var ranges = computeProtectedRanges(s);
  var sb = '', ii = 0;
  while (ii < s.length){
    var r = inProtectedRange(ranges, ii);
    if (r){
      fenceGuarded.push(s.slice(r[0], r[1]));
      sb += '\u000D' + (fenceGuarded.length - 1) + '\u000D';
      ii = r[1];
    } else {
      sb += s[ii];
      ii++;
    }
  }
  s = sb;
  // 只处理“裸路径”：把整段 markdown 链接/图片（![...](url) 或 [...] (url)，含绝对地址）收进占位，
  // 这样其中的 uploads/ 不会被下面的裸路径正则误连，交由 markdown 自己渲染成图/链接。
  var mdGuarded = [];
  s = s.replace(/!?\[[^\]]*\]\(\s*([^\s)]+)\s*\)/g, function(m){
    mdGuarded.push(m);
    return '\u000E' + (mdGuarded.length - 1) + '\u000E';
  });
  // 反斜杠形式：\uploads/… → 去掉反斜杠，但保持文本（标记为不链接）
  s = s.replace(new RegExp("\\\\uploads/(" + U + "*)", 'gi'), function(m, tail){ return '\u000F' + tail; });
  // 裸路径 uploads/… → 占位：图片后缀显示为 <img>，否则为 <a>（均在新标签打开）。
  // 左边紧邻“任意 URL 字符”的 uploads 不做特殊处理（例如 https://…/uploads/… 整条 URL
  // 里的路径段、foo/uploads/… 这类嵌在词中间的），交给 markdown 自身按完整 URL 自动链接；
  // 中文、反引号、空白等非 URL 字符不算，仍视为裸路径。
  s = s.replace(new RegExp("(?<!" + U + ")uploads/(" + U + "+)", 'g'), function(m){
    var sp = splitScale(m);   // 支持尾部 #e / #e1.7 倍率片段（仅对图片生效）
    if (/\.(png|jpe?g|gif|webp|bmp|svg|ico|avif|tiff?)$/i.test(sp.url)){
      var attrs = 'class="uclink" src="' + sp.url + '" alt="' + sp.url + '"'
        + (sp.scale ? ' data-scale="' + sp.scale + '"' : '');
      return storeSentinel('<img ' + attrs + '>');
    }
    return storeSentinel('<a class="uclink" href="' + m + '" rel="noopener" target="_blank">' + m + '</a>');
  });
  s = s.replace(/\u000F/g, 'uploads/');      // 反斜杠形式还原（纯文本）
  s = s.replace(/\u000D(\d+)\u000D/g, function(m, n){ return fenceGuarded[+n] || m; });  // 代码块还原
  s = s.replace(/\u000E(\d+)\u000E/g, function(m, n){ return mdGuarded[+n] || m; });  // markdown 链接/图片还原
  return s;
}

// 对一段“纯文本”（不含 BBCode 标签）做 uploads 占位化
function linkifyUploadsText(t){
  return linkifyUploads(String(t == null ? '' : t));
}

/* 围栏（代码块）整段原样跳过：返回 src 中从 i（围栏起始行行首）到闭合围栏之后的索引。
   若 i 处不是围栏起始，返回 i。用于让 bbcConvert / linkifyUploads / protectEmphasisNewlines
   不处理代码块内部，避免把代码里的 [u]、uploads/、** 等误当成 BBCode/链接/强调。
   闭合规则按 CommonMark：闭合围栏与开启同字符（反引号或波浪线），且数量【不小于】开启的数量
  （开启 ``` 可用 ```` 或更多闭合；这是此前“代码块内仍渲染 bbcode”的根因——旧实现要求
   闭合与开启“完全等长”，闭合更长时就找不到结尾，整个代码块泄漏回常规 markdown）。 */
function fenceEndAt(src, i){
  var lineStart = i;
  // 找 i 所在行的行首（前面是换行或开头）
  while (lineStart > 0 && src[lineStart - 1] !== '\n') lineStart--;
  var rest = src.slice(lineStart);
  var m = /^( {0,3})(`{3,}|~{3,})[^\n]*/.exec(rest);
  if (!m) return i;
  var marker = m[2];
  var ch = marker[0];                       // 反引号或波浪线（决定闭合用哪种字符）
  var minLen = marker.length;               // 开启围栏长度：闭合至少要有这么多
  var after = lineStart + m[0].length;
  // 闭合行：行首≤3 空格 + 同字符、数量≥minLen 的围栏 + 之后仅空白。用多行匹配在余下内容找。
  var closeRe = new RegExp('^ {0,3}' + '[' + (ch === '`' ? '`' : '~') + ']{' + minLen + ',}[ \\t]*$', 'm');
  var cm = closeRe.exec(src.slice(after));
  if (cm) after += cm.index + cm[0].length;
  return after;
}
/* 判断 src 中 lineStart（行首）处是否是一条“围栏行”（开启或闭合）。
   返回 {ch: 反引号或波浪线, len: 该行围栏长度, info: 开启行围栏后的内容(可能是语言/其余字符)，
          end: 该行结束下标(换行前或末尾)}；不是围栏行返回 null。 */
function fenceAtLine(src, lineStart){
  var nl = src.indexOf('\n', lineStart);
  var end = nl === -1 ? src.length : nl;    // 行尾（不含换行）
  var line = src.slice(lineStart, end);
  var m = /^( {0,3})(`{3,}|~{3,})/.exec(line);
  if (!m) return null;
  var ch = m[2][0];
  var len = m[2].length;
  var after = lineStart + m[0].length;       // 围栏之后的内容起点
  return { ch: ch, len: len, after: after, end: end, info: line.slice(m[0].length) };
}
/* 一次扫完 src，按行状态机产出所有 fenced code block 的 [start, end] 区间（升序、不重叠）。
   start/end 都是下标；end 含闭合围栏行（但不含闭合行之后的换行）。
   供 bbcConvert 判断 tag 匹配点是否落在代码块内（逐个字符过状态机，O(n) 不回溯）。 */
function computeFences(src){
  var out = [];
  var i = 0, n = src.length;
  while (i < n){
    var f = fenceAtLine(src, i);              // 当前行是不是围栏行
    if (!f){ var nl = src.indexOf('\n', i); i = nl === -1 ? n : nl + 1; continue; }
    // 开启一行围栏：找与之配对的闭合行
    var open = f, j = f.end + 1;              // 下一行行首（f.end 是行尾，换行在 end）
    if (j > n) j = n;
    // 注意：f.end 指向行尾（换行前）。若行尾即末尾(indexOf 返回 -1)，f.end==n。
    var j2 = j;
    var closed = false, closeEnd = 0;
    while (j2 < n){
      var g = fenceAtLine(src, j2);
      if (g && g.ch === open.ch && g.len >= open.len){ // 闭合：同字符、数量≥开启
        closeEnd = g.end;
        closed = true;
        break;
      }
      var nl2 = src.indexOf('\n', j2);
      j2 = nl2 === -1 ? n : nl2 + 1;
    }
    if (closed){
      out.push([i, closeEnd]);                 // 含整段内容 + 闭合行
      i = closeEnd;                            // closeEnd 行尾
      var nn = src.indexOf('\n', closeEnd);
      i = nn === -1 ? n : nn + 1;              // 跳到闭合行之后
    } else {
      // 未闭合：按非围栏文本推进这一行
      var nl3 = src.indexOf('\n', i);
      i = nl3 === -1 ? n : nl3 + 1;
    }
  }
  return out;
}
/* 位置 pos 是否落在任一 fenced block 内。fences 来自 computeFences（升序）。
   若 pos 恰在某 fence 的 [start, end] 区间内（含 start/end 所在行），返回 true。 */
function fenceContains(fences, pos){
  for (var k = 0; k < fences.length; k++){
    if (pos >= fences[k][0] && pos < fences[k][1]) return true;
    if (fences[k][0] > pos) return false;
  }
  return false;
}
/* 行内码区间：CommonMark 风格——一段连续反引号(长度 N)开启，遇到下一段【长度同为 N】的
   连续反引号闭合；内容(含定界符)在两者之间都视为“禁止解析区”。返回 [start,end) 升序。
   这些区域里的 bbcode/uploads/强调一律不被处理。 */
function computeInlineCode(src){
  var s = String(src || ''), n = s.length, out = [], i = 0;
  while (i < n){
    if (s.charAt(i) === '`'){
      var k = i;
      while (k < n && s.charAt(k) === '`') k++;
      var len = k - i;
      var j = k, found = -1;
      while (j < n){
        if (s.charAt(j) === '`'){
          var m = j;
          while (m < n && s.charAt(m) === '`') m++;
          if ((m - j) === len){ found = j; break; }
          j = m;
        } else { j++; }
      }
      if (found >= 0){ out.push([i, found + len]); i = found + len; continue; }
      i = k;
      continue;
    }
    i++;
  }
  return out;
}
/* 把所有“禁止解析区”合并：代码块(围栏) ∪ 行内码。升序、相邻/重叠合并为一段。
   供 linkifyUploads / protectEmphasisNewlines / bbcConvert 判断某位置是否在禁止区内。 */
function computeProtectedRanges(src){
  var parts = computeFences(src).concat(computeInlineCode(src));
  parts.sort(function(a, b){ return a[0] - b[0]; });
  var merged = [];
  for (var k = 0; k < parts.length; k++){
    var cur = parts[k];
    var last = merged[merged.length - 1];
    if (last && cur[0] <= last[1]){ if (cur[1] > last[1]) last[1] = cur[1]; }
    else merged.push([cur[0], cur[1]]);
  }
  return merged;
}
/* 位置 pos 是否落在任一禁止解析区内；返回所在区间 [start,end)，否则 null。ranges 升序。 */
function inProtectedRange(ranges, pos){
  for (var k = 0; k < ranges.length; k++){
    if (pos >= ranges[k][0] && pos < ranges[k][1]) return ranges[k];
    if (ranges[k][0] > pos) return null;
  }
  return null;
}

function bbcConvert(src){
  var out = '';
  var tagRe = /\[([a-zA-Z]+)(=([^\]]*?))?(\/)?\]/g;
  var i = 0;
  // 先把本段内所有“禁止解析区”（代码块围栏 + 行内码）区间扫出来（逐字符/行状态机，O(n)）。
  // tagRe 会按 [tag] 跨行跳，某次匹配点 openAt 可能落在【前面已有内容】的代码块/行内码内
  //（例如 “111\n````\n[quote]/…\n````”：fence 在 <i 之后、openAt 之前的行，循环位置 i
  // 不在 fence 起始行，fenceEndAt(src,i) 抓不到那条 fence），导致 fence 内的 [tag] 被误当
  // BBCode。因此拿到 openAt 后要显式判断它是否落在某个禁止解析区里。
  var protectedRanges = computeProtectedRanges(src);
  while (i < src.length){
    var fr = inProtectedRange(protectedRanges, i);
    if (fr){                      // i 已在某个禁止解析区内 → 先补前置文本，整段原样输出
      out += src.slice(i, fr[0]);
      out += src.slice(fr[0], fr[1]);
      i = fr[1];
      continue;
    }
    tagRe.lastIndex = i;
    var m = tagRe.exec(src);
    if (!m){ out += src.slice(i); break; }
    var openAt = m.index;
    // 若该 [tag] 匹配点在某个禁止解析区里（前面内容导致该区起始在 i 与 openAt 之间），
    // 则先补 i→区间开头的前置文本，再把该区间整段原样输出（内含的 […] 不被当 BBCode）。
    var hit = inProtectedRange(protectedRanges, openAt);
    if (hit){
      if (hit[0] > i) out += src.slice(i, hit[0]);   // 不能丢掉区间之前的正常文本
      out += src.slice(hit[0], hit[1]);
      i = hit[1];
      continue;
    }
    out += src.slice(i, openAt);
    var tag = m[1].toLowerCase();
    var arg = bbcUnquote(m[3]);
    var selfClose = m[4] === '/';
    var openLen = m[0].length;
    if (selfClose){
      if (tag === 'quote'){
        var html = '<a class="msgquote" data-qid="' + md.utils.escapeHtml(arg) + '" href="#' + md.utils.escapeHtml(arg) + '">…</a>';
        out += storeSentinel(html);
      } else {
        out += '[' + m[1] + (m[3] !== undefined ? '=' + m[3] : '') + '/]';
      }
      i = openAt + openLen; continue;
    }
    // 若 [标签] 紧跟（可含空格）一个 “(”，则是 markdown 链接/图片 [文](url) 或 ![文](url)，
    // 不是 BBCode 标签——整段按纯文本交给 markdown，避免被拆开（如 [x](uploads/a.jpg)）。
    var afterOpen = openAt + openLen;
    var scanP = afterOpen;
    while (scanP < src.length && (src[scanP] === ' ' || src[scanP] === '\t')) scanP++;
    var isMdLink = scanP < src.length && src[scanP] === '(';
    if (isMdLink){
      out += linkifyUploadsText(m[0]);   // 只把 [x] 这部分当文本（链接目的地会在后续文本段里被 uploads 守卫保护）
      i = afterOpen; continue;
    }
    var closeEnd = bbcFindClose(src, openAt, tag, openLen);
    if (closeEnd < 0){ out += src.slice(openAt, openAt + openLen); i = openAt + openLen; continue; }
    var bodyTxt = src.slice(openAt + openLen, closeEnd - (tag.length + 3));
    var inner = bbcConvert(bodyTxt);       // 递归：返回已占位化的源文本 + 写入 __convRep
    if (tag === 'spoiler'){
      if (bbcIsBlockish(bodyTxt)){
        // 块级剧透：前后各留一个空行避免 markdown-it 吞块；外层盒给出边界，内层 core 承载模糊
        out += '\n\n<div class="spoiler" tabindex="0" role="button"><div class="spoiler-core">' + mdRender(inner) + '</div></div>\n\n';
      } else {
        var html = '<span class="spoiler" tabindex="0" role="button"><span class="spoiler-core">' + mdRenderInline(inner) + '</span></span>';
        out += storeSentinel(html);
      }
    } else if (tag === 'details'){
      // 块级折叠：前后空行隔离，确保 <details> 被当作独立 HTML 块、内层 markdown 正常解析
      out += '\n\n<details>\n<summary>' + md.utils.escapeHtml(arg || '') + '</summary>\n' + mdRender(inner) + '\n</details>\n\n';
    } else if (tag === 'u'){
      out += storeSentinel('<u>' + mdRenderInline(inner) + '</u>');
    } else if (tag === 'color'){
      out += storeSentinel('<span style="color:' + md.utils.escapeHtml(arg) + '">' + mdRenderInline(inner) + '</span>');
    } else if (tag === 'quote'){
      out += storeSentinel('<a class="msgquote" data-qid="' + md.utils.escapeHtml(arg) + '" href="#' + md.utils.escapeHtml(arg) + '">…</a>');
    } else {
      // 未识别的标签按字面保留（内层仍会解析 markdown）
      var html = '[' + m[1] + (m[3] !== undefined ? '=' + m[3] : '') + ']' + mdRenderInline(inner) + '[/' + tag + ']';
      out += storeSentinel(html);
    }
    i = closeEnd;
  }
  return out;
}
/* 最简方案：强调标签（粗体/斜体/删除线/下划线）内部出现的任何换行，
   都在 markdown 渲染前替换为私有字符 \u0005，渲染后再统一还原成 <br>。
   用一个“栈扫描器”跟踪当前处于哪些层级的强调内（支持 * 嵌套在 ** 里等任意组合），
   - 在任意一层强调内部的换行/空行 → \u0005，强调即可跨换行配对；
   - 定界符孤悬本行行首/行尾（lineAlone）时，栈能正确判定它是“闭合某个开启的强调”
     还是“开启一层新强调”，避免 markdown 夹配规则导致的字面星号/泄漏。 */
function protectEmphasisNewlines(src){
  var s = String(src || '');
  var tokens = ['**', '__', '~~', '*', '_'];
  var stack = [];              // 当前开启的强调层级（存定界符类型），支持嵌套
  var i = 0, n = s.length, out = '';
  // 禁止解析区（代码块围栏 + 行内码）：其中的 ** / * / _ 等不能当成强调，避免把代码里的换行/标记改坏
  var ranges = computeProtectedRanges(s);
  while (i < n){
    var r = inProtectedRange(ranges, i);
    if (r){ out += s.slice(Math.min(i, r[0]), r[1]); i = r[1]; continue; }
    var ch = s[i];
    if (ch === '\n' && stack.length){ out += '\u0005'; i++; continue; }
    var tok = null;
    for (var t = 0; t < tokens.length; t++){
      if (s.substr(i, tokens[t].length) === tokens[t]){ tok = tokens[t]; break; }
    }
    if (tok){
      var before = i > 0 ? s[i - 1] : '\n';
      var afterIdx = i + tok.length;
      var afterCh = afterIdx < n ? s[afterIdx] : '\n';
      // 栈里是否已有同类型开启的强调
      var openIdx = -1;
      for (var si = stack.length - 1; si >= 0; si--){ if (stack[si] === tok){ openIdx = si; break; } }
      var opener = (before === '\n' || isWhite(before)) && !isWhite(afterCh);
      var closer = !isWhite(before) && (afterCh === '\n' || isWhite(afterCh));
      var lineAlone = (i === 0 || before === '\n' || before === '\u0005') && (afterCh === '\n' || afterCh === '\u0005');
      if (openIdx >= 0 && (closer || lineAlone || afterCh === '\n')){
        stack = stack.slice(0, openIdx);        // 闭合到与该定界符同类型的那层
      } else if (opener || lineAlone){
        stack.push(tok);                        // 开启一层新强调（孤悬且无同类型开启→开启）
      }
      out += tok; i = afterIdx; continue;
    }
    out += ch; i++;
  }
  return out;
}
function isWhite(c){ return c === ' ' || c === '\t' || c === '\n'; }

function bbcToHtml(src){
  __convRep = [];
  // 流程：
  //   1) 先把需换行的强调改写到能配对（protectEmphasisNewlines）
  //   2) 在【完整原文】上做 uploads 链接化（linkifyUploads 在整段上能看到 [文](url)，
  //      既能把裸路径 uploads/… 收进占位，又不会误连已写在 markdown 链接/图片里的路径）
  //   3) bbcConvert 负责 BBCode 与保留 markdown 链接（[x](…) 不会被误当标签）
  //   4) mdRender 展开占位；最后把新行占位还原为真实 <br>。
  var pre = linkifyUploads(protectEmphasisNewlines(src || ''));
  var html = mdRender(bbcConvert(pre));
  // \u0005 = 强调内部被替换的换行，全部还原为 <br>
  html = html.split('\u0005').join('<br>');
  return html;
}


function renderRich(src){
  let html = '';
  try {
    html = bbcToHtml(normalizeAmbiguities(src || ''));
  } catch(e){ html = '<p>『渲染出错』</p>'; }
  const target = document.createElement('div');
  target.className = 'rich';
  // DOMPurify 放行猎户座编辑器扩展所需标签/属性。整个文档先包一层根 div 再净化，
  // 可避免 <details> 等块元素紧邻“脚注块”时被解析器意外丢弃的问题，净化后再拆开根 div。
  const root = document.createElement('div');
  root.className = 'root';
  const frag = DOMPurify.sanitize(
    '<div class="root" data-root="1">' + html + '</div>',
    {
      RETURN_DOM_FRAGMENT: true,
      ADD_TAGS: ['details', 'summary', 'u', 'span', 'div', 'input', 'hr', 'sup', 'ol', 'li'],
      ADD_ATTR: ['data-theme-scrollable', 'role', 'color', 'data-root', 'data-qid', 'target', 'rel', 'data-scale'],
      FORBID_TAGS: ['style', 'script', 'iframe', 'object', 'embed', 'form']
    }
  );
  const inner = frag.querySelector('[data-root="1"]') || frag;
  Array.from(inner.childNodes).forEach(n => target.appendChild(n));
  // 绑定剧透点击：循环切换展开/遮罩
  target.querySelectorAll('.spoiler').forEach(function(el){
    el.addEventListener('click', function(){ el.classList.toggle('revealed'); });
    el.addEventListener('keydown', function(e){ if(e.key==='Enter'||e.key===' '){e.preventDefault();el.classList.toggle('revealed');} });
  });
  // 代码块：复制按钮 + 展开/收起
  target.querySelectorAll('.codeblock').forEach(function(box){
    const copyBtn = box.querySelector('.codeblock-copy');
    const toggleBtn = box.querySelector('.codeblock-toggle');
    const codeEl = box.querySelector('code');
    if (copyBtn && codeEl){
      copyBtn.addEventListener('click', function(){
        // 取原始代码文本（code 里是已高亮的 HTML，textContent 即纯代码）
        copyText(codeEl.textContent || '');
        copyBtn.textContent = '已复制';
        setTimeout(function(){ copyBtn.textContent = '复制'; }, 1200);
      });
    }
    if (toggleBtn){
      toggleBtn.addEventListener('click', function(){ box.classList.toggle('collapsed'); });
    }
  });
  // 引用：异步填充被引用消息摘要，点击跳转
  target.querySelectorAll('.msgquote').forEach(function(el){
    const id = el.getAttribute('data-qid');
    el.classList.add('loading');
    el.textContent = '加载引用…';
    resolveQuote(el, id);
    el.addEventListener('click', function(ev){ ev.preventDefault(); ev.stopPropagation(); jumpToMessage(id); });
  });
  scaleImages(target);       // 复制 data-scale → --scale（图片倍率交给 CSS 计算）
  return target;
}

/* 图片倍率（#e 结尾）：把 data-scale 的数值写入元素 CSS 变量 --scale。
   CSS(.rich img) 用 width:min(100%,100%*var(--scale),600px*var(--scale)) 计算尺寸；
   之所以用 JS 读属性→写变量，而不是 CSS attr()：attr(data-scale unit,1) 的数值/单位
   语法在实现上会恒返回 1（不读实际值）。 */
function applyImgScale(img){
  const f = parseFloat(img.getAttribute('data-scale'));
  img.style.setProperty('--scale', (f > 0 ? String(f) : '1'));
}
function scaleImages(root){
  if (!root) return;
  root.querySelectorAll('img[data-scale]').forEach(applyImgScale);
}

/* 图片倍率完全由 CSS 完成：.rich img 直接读取 DOM 属性 data-scale（--scale, 缺省 1），
   无需 JS 计算尺寸，也不依赖图片加载完成。不再需要 applyImgScale/scaleImages。 */

/* ---------- 工具 ---------- */
function fmtTime(ts){
  const d = new Date(ts*1000);
  const p = n => String(n).padStart(2,'0');
  return p(d.getMonth()+1)+'/'+p(d.getDate())+' '+p(d.getHours())+':'+p(d.getMinutes());
}
function esc(s){
  return String(s).replace(/[&<>"]/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]));
}
/* 复制文本到剪贴板（含降级） */
function copyText(text){
  let usedClip = false;
  try {
    if (navigator.clipboard && navigator.clipboard.writeText){
      navigator.clipboard.writeText(String(text)).then(function(){}).catch(function(){ fallbackCopy(text); });
      usedClip = true;
    }
  } catch(e){ usedClip = false; }
  if (!usedClip) fallbackCopy(text);
}
function fallbackCopy(text){
  const ta = document.createElement('textarea');
  ta.value = text;
  ta.style.position='fixed'; ta.style.opacity='0';
  document.body.appendChild(ta);
  ta.select();
  try { document.execCommand('copy'); } catch(e){}
  document.body.removeChild(ta);
}
function api(path){
  // 把 “api/<name>?其他参数” 转成 “?r=<name>&其他参数&p=<凭证>”
  let p = String(path);
  let qs = '';
  const qi = p.indexOf('?');
  if (qi >= 0){ qs = p.slice(qi + 1); p = p.slice(0, qi); }
  const name = p.replace(/^api\//, '');
  let out = '?r=' + encodeURIComponent(name);
  if (qs) out += '&' + qs;
  out += '&p=' + encodeURIComponent(CODE);
  return out;
}
function focusEditor(){ const e=document.getElementById('editor'); e.focus(); }
/* 粗略判断触屏设备（手机/平板）：粗指针或高触点数视为触屏 */
function isTouchDevice(){
  try {
    if (window.matchMedia && matchMedia('(pointer: coarse)').matches) return true;
  } catch(e){}
  return (navigator.maxTouchPoints || 0) > 1 || ('ontouchstart' in window);
}

/* 移动端：把页面高度锁定为加载时测量的固定像素值。
   虚拟键盘弹出只会触发 resize/visualViewport 变化、不会触发 orientationchange，
   因此锁定后键盘弹出也不会改变显示高度。桌面手动缩放仍会重锁。 */
function pinLayoutHeight(){
  const h = window.innerHeight;
  document.documentElement.style.height = h + 'px';
  document.body.style.height = h + 'px';
  const app = document.getElementById('app');
  if (app) app.style.height = h + 'px';
}

/* ================================================================
 *  引用 & 跳转 & 搜索
 * ================================================================ */
/* 取消息纯文本前 n 个“实字”：去掉 markdown/bbcode/html 记号，换行等空白折叠为空格 */
function plainPreview(content, n){
  let s = String(content || '');
  s = s.replace(/\[(?:quote|u|color|spoiler|details|b|i)[^"\]]*"[^"]*"[^\]]*\]/gi, ' ')
       .replace(/\[(?:quote|u|color|spoiler|details|b|i)[^\]]*\]/gi, ' ')
       .replace(/\[\/(?:quote|u|color|spoiler|details|b|i)\]/gi, ' ');
  s = s.replace(/`{1,3}/g, ' ').replace(/\*{1,3}|_{1,3}/g, '')
       .replace(/[#>{|~]{1,3}/g, ' ').replace(/^[-\s]+(?=\w)/gm, ' ');
  s = s.replace(/!?\[([^\]]*)\]\([^)]*\)/g, '$1');   // 链接/图片 -> 显示文本
  s = s.replace(/<\/?[^>]+>/g, ' ');                 // html 标签
  s = s.replace(/\s+/g, ' ').replace(/^[\s\s]+|[\s\s]+$/g, '').trim();
  return s.slice(0, n);
}

/* 往编辑器光标处插入“引用该消息”代码 */
function insertQuoteInEditor(id){
  const w = document.getElementById('editor');
  const s = w.selectionStart, en = w.selectionEnd;
  w.setRangeText('[quote="' + id + '"/]', s, en, 'end');
  w.focus(); renderPreview(); updateCharCount();
}

/* 工具栏“引用消息”：插入 [quote=""/]，光标落在 id 内便于直接输入消息id */
function insertQuoteTool(w){
  const s = w.selectionStart, en = w.selectionEnd;
  const t = '[quote=""/]';
  w.setRangeText(t, s, en, 'end');
  w.setSelectionRange(s + '[quote="'.length, s + '[quote="'.length);
  w.focus(); renderPreview(); updateCharCount();
}

/* 渲染引用链接：显示被引用消息的前 10 个“实字”+…；点击跳转该消息 */
function resolveQuote(el, id){
  id = String(id);
  const hit = MSGS.find(m => String(m.id) === id);
  if (hit){ el.textContent = plainPreview(hit.content, 10) + '…'; el.classList.remove('loading'); return; }
  function removed(){
    el.classList.add('removed');
    el.classList.remove('loading');
    el.removeAttribute('href');          // 已删除的引用不可点击跳转
  }
  fetch(api('messages?minId=' + id + '&maxId=' + id))
    .then(r => r.json())
    .then(j => {
      const m = j && j.slice && j.slice[0];
      if (m && String(m.id) === id){ el.textContent = plainPreview(m.content, 10) + '…'; el.classList.remove('loading'); }
      else { el.textContent = '(已删除 #' + id + ')'; removed(); }
    })
    .catch(() => { el.textContent = '(无法加载 #' + id + ')'; removed(); });
}
/* 某条消息被发现已删除后：把 DOM 里所有引用它的 .msgquote 也标为“已删除不可点”，重新渲染。
   供 pollSlice / pollNew 在删除对账时调用，保证引用与消息状态一致。 */
function markQuotesDeleted(id){
  id = String(id);
  chat.querySelectorAll('.msgquote[data-qid="' + CSS.escape(id) + '"]').forEach(function(el){
    el.textContent = '(已删除 #' + id + ')';
    el.classList.add('removed');
    el.classList.remove('loading');
    el.removeAttribute('href');
  });
}
/* 某条消息被确认“现存”（新收到/刷新拉到）时：把 DOM 里所有引用它的片段恢复为可引用，
   并重新填充摘要（之前可能因一度未加载而标成已删除）。 */
function markQuotesAlive(id){
  id = String(id);
  chat.querySelectorAll('.msgquote[data-qid="' + CSS.escape(id) + '"]').forEach(function(el){
    el.classList.remove('removed');
    el.classList.add('loading');
    el.setAttribute('href', '#' + id);
    resolveQuote(el, id);        // 重填摘要（消息在 MSGS 里会直接显示内容）
  });
}

/* 跳到某条消息：只把视口切到目标消息附近并高亮 1 秒。
   流程：先完成一切计算/异步加载（此期间不清空 DOM，避免闪现“加载中”空界面），
   等目标附近片就绪后，再一次性清空 + 渲染 + 滚动。 */
async function jumpToMessage(id){
  id = String(id);
  try {
    // around 规则：
    //   1) 锚点是否存在于 MSGS？不存在 → fetch 分支。
    //   2) 存在 → 检查左右能否数出共 CHUNK 条且不跨越未 fetch 的消息。
    //      能 → 渲染；不能 → fetch 分支（拉 12 条），重新数，渲染。
    const nid = Number(id);
    const anchorExists = MSGS.some(x => String(x.id) === String(id));
    let slice = null;
    if (anchorExists){
      let r = countAround(nid);
      if (!r.crossedGap){
        slice = r.msgs;
      } else {
        // 跨越 → 真实拉 around，重数后若仍跨越，只渲染已 covered 的子集（不渲跨区）
        await fetchAround(id);
        const r2 = countAround(nid);
        slice = r2.crossedGap ? r2.msgs.filter(m => coveredById(Number(m.id))) : r2.msgs;
      }
    } else {
      // 锚点不存在 → 真实拉 around
      await fetchAround(id);
      const r2 = countAround(nid);
      slice = r2.crossedGap ? r2.msgs.filter(m => coveredById(Number(m.id))) : r2.msgs;
    }
    // —— slice 就绪后，才一次性切换视口（不会出现中间空界）——
    chat.innerHTML = '';
    topSeq = Infinity; bottomSeq = -1;
    (slice || []).forEach(function(m){
      bottomSeq = m.seq;
      if (topSeq === Infinity) topSeq = m.seq;
      appendMessageNode(m);
    });
    updateOlderButton();
    atBottom = false;
    requestAnimationFrame(function(){
      const row = chat.querySelector('.msg[data-id="' + CSS.escape(id) + '"]');
      if (row){
        row.scrollIntoView({ block: 'center', behavior: 'auto' });
        row.classList.add('highlight');
        setTimeout(function(){ row.classList.remove('highlight'); }, 1000);
      }
    });
  } catch(e){}
}
/* fetch around(id) 一批并入 MSGS；数量 = CHUNK（一切数量都应是 chunk 的整数倍）。 */
var AROUND_N = CHUNK;
async function fetchAround(id){
  const res = await fetch(api('messages?around=' + id + '&limit=' + AROUND_N));
  const j = await res.json();
  if (j && j.ok){
    noteLatest(j.slice);
    noteFirstId(j);
    (j.slice || []).forEach(function(m){
      if (MSGS.findIndex(x => String(x.id) === String(m.id)) < 0) MSGS.push(m);
    });
    if (j.slice && j.slice.length){
      // around 返回即以目标为中心的一片：联合真实返回 [min,max]
      unionRanges(
        Math.min.apply(null, j.slice.map(x => Number(x.id))),
        Math.max.apply(null, j.slice.map(x => Number(x.id)))
      );
    }
    return j.slice || [];
  }
  return [];
}
/* 检查 [lo, hi] 是否整个被 fetchedRanges 覆盖（含删除空洞——删除的消息在被删前已 fetch，
   该 id 区间仍然可信）。用覆盖区间判断：一个覆盖区间的起点 ≤ lo 且终点 ≥ hi 即可。 */
function rangeFullyCovered(lo, hi){
  if (hi < lo) return true;
  for (const r of fetchedRanges){
    if (r[0] <= lo && r[1] >= hi) return true;
  }
  return false;
}
/* 以 anchor 为中心，向左右走步数最多 CHUNK 条现存消息。
   不做 id 加减：从锚点的跳表节点沿 prev/next 指针走。走完拿最小/最大 id，
   若整个 [min,max] 被 fetchedRanges 覆盖则不需 fetch（crossedGap=false），否则需要 fetch。 */
function countAround(anchor){
  const msgs = [];
  let crossedGap = false;
  const side = CHUNK;   // 每侧最多 CHUNK 条；尽量多数连续，fetch 不足则渲多少算多少
  // 右（含 anchor）：沿 next 走 side 条现存。每步检查与下一节点之间间隔是否整体 covered
  //（删除的空洞已被 fetchedRanges 覆盖则信任；未 fetch 的间隔立即停，不跨）。
  let right = [];
  let node = MSGS.node(anchor);
  if (!node) return { msgs: [], crossedGap: true };
  right.push(node.msg);
  let cur = node;
  while (right.length < side && cur.next !== MSGS.tail){
    const nx = cur.next;
    if (!rangeFullyCovered(cur.id + 1, nx.id - 1)){ crossedGap = true; break; }
    right.push(nx.msg);
    cur = nx;
  }
  // 左：沿 prev 走 side 条现存，同样每步检查间隔。
  let left = [];
  cur = node;
  while (left.length < side && cur.prev !== MSGS.head){
    const pv = cur.prev;
    if (!rangeFullyCovered(pv.id + 1, cur.id - 1)){ crossedGap = true; break; }
    left.unshift(pv.msg);
    cur = pv;
  }
  msgs.push.apply(msgs, left);
  msgs.push.apply(msgs, right);
  // 走完后整体检查 [min,max] 是否被 fetchedRanges 包含（删除空洞仍可信）
  if (msgs.length){
    const ids = msgs.map(m => Number(m.id));
    const lo = Math.min.apply(null, ids), hi = Math.max.apply(null, ids);
    if (!rangeFullyCovered(lo, hi)) crossedGap = true;
  }
  return { msgs, crossedGap };
}

/* “回到最下”：以 bottomId()（最后一个没被删过、已加载的消息）为目标。
   不用 latestId：latestId 可能指向一条已被删的消息，永远不在 DOM，会导致只前进/跳转
   到一个已不存在的 id。bottomId() 是 MSGS 真实尾部的现存最大值。
   目标已在 DOM → 直接贴底；否则跳到 bottomId() 区段后再贴底。 */
async function goToBottom(){
  const target = bottomId() || latestId || 1;
  const row = target ? chat.querySelector('.msg[data-id="' + CSS.escape(String(target)) + '"]') : null;
  if (row || (!bottomId() && !latestId)){
    // 目标是已加载的最新，或尚无可跳转的（空状态）→ 直接贴底
    atBottom = true;
    stickBottom();
    hideNewMsgIfBottom();
  } else {
    // 跳转：jumpToMessage 保留 fetchedRanges(只增不减)，从 MSGS/必要时服务器重建目标附近 DOM。
    await jumpToMessage(String(target));
    // jumpToMessage 已加载最新区段并居中；这里在下一帧之后贴底，确保滚到真正最后一条
    requestAnimationFrame(function(){
      requestAnimationFrame(function(){
        atBottom = true;
        stickBottom();
        hideNewMsgIfBottom();
      });
    });
  }
}

/* ---------- 消息列表 DOM ---------- */
const chat = document.getElementById('chat');
function scrollTop(){ return chat.scrollTop; }
function scrollBottomGap(){ return chat.scrollHeight - chat.scrollTop - chat.clientHeight; }
function stickBottom(){ chat.scrollTop = chat.scrollHeight; }

/* 是否“贴底”：次新“最后一个没被删过的消息”（bottomId()，=已加载现存最大）在 DOM 中，
   且距底部 <5px，二者同时满足才算。用 bottomId() 而非 latestId：latestId 所指那条一旦被删除
   就永远不在 DOM，会误判“未贴底”。 */
function isAtBottom(){
  const gap = chat.scrollHeight - chat.scrollTop - chat.clientHeight;
  if (gap > 5) return false;
  const b = bottomId();
  if (b == null || b <= 0) return true;              // 还没有已知的最新消息，视为贴底
  return !!chat.querySelector('.msg[data-id="' + CSS.escape(String(b)) + '"]');
}

function buildMessage(msg){
  const row = document.createElement('div');
  row.className = 'msg ' + (msg.author === ME.name ? 'mine' : 'other');
  // 访客一律在左
  if (ME.name === '访客') row.className = 'msg other';
  row.dataset.id = msg.id;
  row.dataset.seq = msg.seq;
  row.dataset.author = msg.author;

  const meta = document.createElement('div');
  meta.className = 'meta';
  let nm = '<span class="name">' + esc(msg.author) + '</span>';
  if (msg.time) nm += '<time>' + fmtTime(msg.time) + '</time>';
  nm += ' <span class="msgid">#' + esc(String(msg.id)) + '</span>';
  nm += ' <button class="quoteSrc" title="引用该消息">引用该消息</button>';
  nm += ' <button class="copySrc" title="复制该消息原始代码">复制</button>';
  meta.innerHTML = nm;
  // 复制消息原始代码到剪贴板
  meta.querySelector('.copySrc').addEventListener('click', function(ev){
    ev.stopPropagation();
    const btn = this;
    const old = btn.textContent;
    btn.textContent = '已复制';
    try { copyText(msg.content || ''); } catch(e){}
    setTimeout(()=>{ btn.textContent = old; }, 1200);
  });
  // 引用该消息：往编辑器插入 [quote="<id>"/]
  meta.querySelector('.quoteSrc').addEventListener('click', function(ev){
    ev.stopPropagation();
    insertQuoteInEditor(String(msg.id));
  });
  row.appendChild(meta);

  const content = document.createElement('div');
  content.className = 'content';
  row.appendChild(content);
  // 服务器已在切片中返回富文本内容，直接渲染
  setContentEl(content, msg.content || '');

  // 本人且可发言的消息始终显示删除按钮
  if (ME && ME.canSend && msg.author === ME.name) addDeleteBtn(row);
  return row;
}

function addDeleteBtn(row){
  const b = document.createElement('button');
  b.className = 'del';
  b.textContent = '删除';
  b.addEventListener('click', function(){ confirmDelete(row.dataset.id); });
  row.appendChild(b);
}

function setContentEl(contentWrap, source){
  contentWrap.innerHTML = '';
  // 把富文本包进 .bubble，令既有 .msg .bubble 样式（边框/内边距/自动换行）真正生效
  const bubble = document.createElement('div');
  bubble.className = 'bubble';
  bubble.appendChild(renderRich(source));
  contentWrap.appendChild(bubble);
}

/* 服务器始终随切片返回 content，此分片懒加载接口保留备用 */
function loadContent(row){
  const id = row.dataset.id;
  fetch(api('api/content?id=' + id)).then(r=>r.json()).then(j=>{
    if (j && j.ok){
      const content = contentWrapOf(row);
      setContentEl(content, j.content || '');
    }
  }).catch(()=>{});
}
function contentWrapOf(row){ return row.querySelector('.content'); }

/* ================================================================
 *  消息加载核心
 *  游标约定：
 *   - MSGS      已加载消息，按 seq 升序（seq 即消息在服务器中的序号，0 起）
 *   - fetchedRanges  全局“已拉取区段”表：互不重叠的有序 [lo,hi] id 区间（取并集）。
 *             只有且当视口来到某个“未拉取的区段”时才拉取，拉完并入该表。
 *   - totalCount 服务器最新总条数
 *   - topSeq     已加载最旧一条的 seq（>=0）；全部已加载时可为 0
 *   - bottomSeq  已加载最新一条的 seq；初始 -1
 *   - atBottom   用户是否贴底（距底 <=5px）
 *  服务器接口：一">"次只回一片；滚动触发翻片时会检查未拉区段。
   * ================================================================ */

/* 已拉取区段表（取并集，保持排序不重叠） */
let fetchedRanges = [];
function unionRanges(lo, hi){
  if (lo > hi) return;
  let stack = [[lo, hi]];
  for (const [a, b] of fetchedRanges){
    if (b + 1 < stack[stack.length-1][0] || a - 1 > stack[stack.length-1][1]){
      stack.push([a, b]);
    } else {
      stack[stack.length-1] = [ Math.min(stack[stack.length-1][0], a), Math.max(stack[stack.length-1][1], b) ];
      // 与更前面的段也可能合并
    }
  }
  // 重新规整：排序后合并相邻/重叠
  stack.sort((x, y) => x[0] - y[0]);
  const merged = [];
  for (const [a, b] of stack){
    if (merged.length && a <= merged[merged.length-1][1] + 1){
      merged[merged.length-1][1] = Math.max(merged[merged.length-1][1], b);
    } else {
      merged.push([a, b]);
    }
  }
  fetchedRanges = merged;
}
function coveredById(id){
  for (const [a, b] of fetchedRanges){ if (id >= a && id <= b) return true; }
  return false;
}
/* 对 dir=up/down 请求，把“锚点到远端”整段记为已拉取区段。
   服务器从锚点往一个方向连续取消息(排除已删)，但锚点本身可能已被删除而不会返回。
   因此可信区间永远是“锚点 → 本次返回的最远端”，不会只是零散的返回 id 子段：
     - dir=down & anchor=B ：联合 [B, 返回max]（锚点是下界）
     - dir=up   & anchor=T ：联合 [返回min, T]（锚点是上界）
   若本次无返回，则仅联合锚点自身一处。 */
function unionAnchorRange(anchor, recvIds, dir){
  const a = Number(anchor);
  if (isNaN(a)) return;
  const ids = (recvIds || []).map(n => Number(n)).filter(n => !isNaN(n));
  if (!ids.length){ unionRanges(a, a); return; }
  const min = Math.min.apply(null, ids);
  const max = Math.max.apply(null, ids);
  if (dir === 'down'){
    unionRanges(a, Math.max(a, max));   // 底界=锚点(可信端)，上界=返回最大
  } else if (dir === 'up'){
    unionRanges(Math.min(a, min), a);   // 上界=锚点(可信端)，下界=返回最小
  } else { /* around：目标两端都取，合并为 [min, max]，与锚点无关（本就以目标为中心） */
    if (ids.length) unionRanges(min, max);
  }
}
/* 把“实际渲染的这一批消息”按其真实 id 归入已拉取区段（jumpToMessage around / render 补片用）。 */
function unionSliceIds(msgs){
  const ids = (msgs || []).map(m => Number(m.id)).filter(n => !isNaN(n)).sort((a,b)=>a-b);
  if (!ids.length) return;
  // 把升序 id 切成连续段，逐段 unionRanges
  let runStart = ids[0], runEnd = ids[0];
  for (let i = 1; i < ids.length; i++){
    if (ids[i] === runEnd + 1){ runEnd = ids[i]; }
    else { unionRanges(runStart, runEnd); runStart = ids[i]; runEnd = ids[i]; }
  }
  unionRanges(runStart, runEnd);
}
/* 返回包含该 id 的已拉取区段，否则 null */
function rangeOf(id){
  for (const r of fetchedRanges){ if (id >= r[0] && id <= r[1]) return r; }
  return null;
}

function appendMessageNode(m){
  const row = buildMessage(m);
  chat.appendChild(row);
  return row;
}

/* 顶部“加载更早的消息”块是否显示 */
function hasMoreOlder(){ return topSeq > 0 && totalCount > MSGS.length; }

/* 顶部“加载中”指示：仅在滚到聊天顶部且有更早消息时出现（无按钮，自动翻片），
   一旦离开顶部即移除，避免在底部/中途一直显示“加载中…”造成“永远加载不完”的错觉。 */
function updateOlderButton(){
  let loader = chat.querySelector('.loader');
  const nearTop = chat.scrollTop <= 40;
  const show = nearTop && hasMoreOlder();
  if (show && !loader){
    loader = document.createElement('div');
    loader.className = 'loader';
    loader.textContent = '加载中…';
    chat.prepend(loader);
  } else if (!show && loader){
    loader.remove();
  }
}

/* 初始/重建：拉最新一片（序列最大的一批）并贴到底部 */
async function fullReload(){
  // 用很大的 upto 让服务端返回“最新”的一片（做旧的翻片方向才能拿到新片）
  const res = await fetch(api('api/messages?upto=' + 2147483647 + '&limit=' + CHUNK));
  const j = await res.json();
  if (!j.ok) return;
  totalCount = j.count;
  lastSeenCount = j.count;
  noteLatest(j.slice);                        // 记全局最大 id
  noteFirstId(j);                             // 记全局最小现存 id（一进入即确立边界）
  chat.innerHTML = '';
  MSGS = makeSkipList();
  fetchedRanges = [];                          // 重置已拉取区段
  topSeq = Infinity;
  bottomSeq = -1;
  (j.slice||[]).forEach(m => {
    MSGS.push(m);
    bottomSeq = m.seq;
    if (topSeq === Infinity) topSeq = m.seq;
    appendMessageNode(m);
  });
  // 把本次已拉取区段并入区段表（upto=实际返回的这批；整个 [返回min, 返回max] 都可信，
  // 含被删的 id——不能用 unionSliceIds 按现存连续段 union，否则会拆碎区间）
  if (j.slice && j.slice.length){
    const ids = j.slice.map(m => Number(m.id));
    unionRanges(Math.min.apply(null, ids), Math.max.apply(null, ids));
  }
  updateOlderButton();
  atBottom = true;
  requestAnimationFrame(stickBottom);
}

/* 轮询拆分为两路，互不冲突：
   1) 新消息轮询 pollNew：只拉比 bottomSeq 新的消息，仅“新增”，不改动已有行。
   2) 分片轮询 pollSlice：只拉“当前可见的最新一片”，仅“修改该范围内的行”
      （内容变化就地刷新、被删除的移除），不新增。
   两路各用独立 busy 标志，避免互相覆盖。 */
let lastSeenCount = 0;
let busyNew = false, busySlice = false;
let tickNew = 0, tickSlice = 0;      // 各轮询最近一次触发时间（防卡死看门狗）

function runPolls(){
  // 看门狗：某路 busy 标记卡住超过 6s（请求挂起/异常）则强制复位，避免 GUI 停止更新
  const now = Date.now();
  if (busyNew && now - tickNew > 6000) busyNew = false;
  if (busySlice && now - tickSlice > 6000) busySlice = false;
  // 绝不抛出：即使某一轮失败也要保证后续继续轮询
  Promise.resolve().then(function(){ return pollNew(); }).catch(function(){});
  Promise.resolve().then(function(){ return pollSlice(); }).catch(function(){});
}

/* 已加载消息中 id 最小的（最旧）与最大的（最新） */
/* 已加载消息中 id 最小的（最旧）与最大的（最新）。
   MSGS 是权威内存表：所有消息都先进入 MSGS，再同步到 DOM，
   因此直接以 MSGS 为准即可。 */
// 注意：MSGS 里 x.id 是服务端返回的字符串，若按字符串比较会字典序出错
// （'9' > '100'），统一转成 Number 再比较，返回数字类型。
function topId(){ return MSGS.minId(); }
function bottomId(){ return MSGS.maxId(); }

/* 全局最大 id：任何获取到的消息都会即时更新 latestId（只增不减）。
   新消息轮询独立于分片/视口刷新，始终以全局最大 id 为锚，永不停止地拉真·新消息。 */
function noteLatest(arr){
  (arr || []).forEach(m => { const id = Number(m.id); if (id > latestId) latestId = id; });
}
/* 记录服务端全局最小现存 id：若其在本地缓存里已不存在（被删），则退到本地现存最小去自修正。 */
function noteFirstId(j){
  if (j && typeof j.firstId === 'number') globalEarliestId = j.firstId;
  else if (j && j.firstId) globalEarliestId = Number(j.firstId);
}

/* 新消息轮询：dir=down，锚点 = 全局最大消息 id（latestId）。独立、持续运行。 */
async function pollNew(){
  if (!ME) return;
  // 自愈看门狗：某次请求挂起超过 6s 则强制复位，保证新消息获取永不停止
  if (busyNew && (Date.now() - tickNew > 6000)) busyNew = false;
  if (busyNew) return;
  busyNew = true; tickNew = Date.now();
  try {
    const bId = latestId || bottomId() || 0;        // 全局最大 id；无则回退内存最大
    const res = await fetch(api('api/messages?dir=down&min=' + CHUNK + '&anchor=' + bId));
    const j = await res.json();
    if (!j || !j.ok) return;
    totalCount = j.count;
    // —— 判定应在更新 any 状态前做 ——
    const beforeScrollTop = chat.scrollTop;
    const gapBefore = chat.scrollHeight - chat.scrollTop - chat.clientHeight;
    // 插入条件：次新（“最后一个没被删过、已加载的消息” = bottomId()）是否已在 DOM 里。
    // 若已在 DOM，说明尾部连续，把新消息接上去；否则不硬插（避免视口出现未拉区段空洞）。
    // 注意不能用“DOM 现存最大 id”来判：它永远在 DOM 里，恒为 true——
    // 必须用 MSGS 记忆的尾部 bottomId() 与 DOM 核对。MSGS 需正确移除已删消息才能反映现状。
    const prevBottomId = bottomId();                 // 本次拉取前的次新（MSGS 尾部）
    const bottomInDom = (prevBottomId == null || prevBottomId <= 0) ||
      !!(chat.querySelector('.msg[data-id="' + CSS.escape(String(prevBottomId)) + '"]'));
    const canInsertNewest = bottomInDom || j.count === 0;
    // 是否滚到底：只有“插入了 DOM”且“插入前本来就在底部（距底<5px）”才自动滚到底。
    // 单独判断插入前是否贴底，避免误把“未插入”当成已贴底。
    const wasAtBottomBefore = gapBefore <= 5 && bottomInDom;
    noteLatest(j.slice);                            // 收到即更新全局最大 id
    // 尾部删除对账：本次拉取 anchor(<bId) 之后的最旧一条是 slice[0]。
    // 若二者间存在“客户端仍持有、但服务器已不返回”的消息（bId+1 … slice[0]-1），说明它们已被删除，
    // 把它们从 MSGS 与 DOM 一起移除，保证 bottomId()（=最后一条没被删过）始终正确。
    if (j.slice && j.slice.length){
      const firstId = Number(j.slice[0].id);
      const bN = Number(bId) || 0;               // bId 可能是 latestId(数字)或 bottomId()(数字)
      const stale = MSGS.filter(x => { const n = Number(x.id); return n > bN && n < firstId; });
      stale.forEach(function(x){
        const row = chat.querySelector('.msg[data-id="' + CSS.escape(String(x.id)) + '"]');
        if (row) row.remove();
        markQuotesDeleted(x.id);                       // 被删消息的引用一并标为不可点
        MSGS.remove(Number(x.id));
      });
    }
    const existingIds = new Set(Array.from(chat.querySelectorAll('.msg')).map(r => r.dataset.id));
    let appended = 0, othersNew = 0;                  // othersNew: 其中“他人”的新消息数
    let lastOther = null;                             // 最新一条“他人”新消息（用于系统通知）
    // 收/发统一流程：
    //   1) 任何处置前，已按当前 DOM 现状判出 canInsertNewest（次新 bottomId 在 DOM）。
    //   2) 只在“次新在 DOM”时插入到 DOM。
    //   3) 插入了 DOM 且插入前本就在底部，才滚到底。
    //   4) 无论是否插入，都放回 MSGS（保证内存表完整、bottomId() 正确反映已收到的最新）。
    (j.slice||[]).forEach(m => {
      if (existingIds.has(String(m.id))) return;      // 已在 DOM/MSGS 的跳过
      if (m.author && ME && m.author === ME.name){ /* 自己的消息不触发“有新消息” */ }
      else { othersNew++; lastOther = m; }
      if (canInsertNewest){ MSGS.push(m); appendMessageNode(m); existingIds.add(String(m.id)); appended++; }
      else { MSGS.push(m); }                            // 不插入 DOM，但照常进 MSGS
      markQuotesAlive(m.id);                            // 该消息现存：视觉内引用其的片段恢复可引用
    });
    if (othersNew > 0 && lastOther){
      notifyNewMessage(lastOther.author || '有人', plainPreview(lastOther.content || '', 60));
    }
    if (j.slice && j.slice.length){
      const last = j.slice[j.slice.length-1];
      const lastNode = MSGS.node(Number(last.id));
      if (lastNode) bottomSeq = lastNode.msg.seq;
      // dir=down(锚点=全局最新)：联合“锚点到远端”整体（锚点端可信，哪怕锚点被删）
      unionAnchorRange(bId, j.slice.map(m => Number(m.id)), 'down');
    }
    if (appended > 0){
      // 滚到底 = 已插入 DOM 且插入前就在底部；二者都满足才自动滚到底
      if (wasAtBottomBefore){
        requestAnimationFrame(stickBottom);
        hideNewMsgIfBottom();                 // 到底即清“有新消息”
      } else if (othersNew > 0) {
        chat.scrollTop = beforeScrollTop;
        showNewMsgLabel();
      } else {
        chat.scrollTop = beforeScrollTop;         // 只是自己的新消息：不提示
      }
    } else if (othersNew > 0){
      // 有新消息(他人)但次新不在 DOM：只提示“有新消息”，不插入视口
      showNewMsgLabel();
    }
    lastSeenCount = j.count;
  } finally { busyNew = false; }
}

/* 分片轮询：轮询“当前视口内正好显示的 id 区间 [minId, maxId]”，让取回段与视口完全匹配。
   取回该 id 区间内所有现存消息（删除造成的编号空洞不影响，只返回仍存在的），
   只修改该 id 区间范围内的已有行：内容变化就地刷新；该区间内存在但取回结果里没有 → 已被删除 → 移除。 */
async function pollSlice(){
  if (!ME) return;
  // 自愈看门狗：请求挂起超过 6s 强制复位，避免视口刷新停滞
  if (busySlice && (Date.now() - tickSlice > 6000)) busySlice = false;
  if (busySlice) return;
  busySlice = true; tickSlice = Date.now();
  try {
    const vis = visibleSliceInfo();                 // 视口内可见消息：{minId, maxId}
    if (!vis) return;
    // 记录视口相关消息的全局最大 id
    const rows = chat.querySelectorAll('.msg');
    noteLatest(Array.from(rows).map(r => ({ id: Number(r.dataset.id) })));
    if (!vis) return;
    const res = await fetch(api('api/messages?minId=' + vis.minId + '&maxId=' + vis.maxId));
    const j = await res.json();
    if (!j || !j.ok) return;
    totalCount = j.count;
    noteFirstId(j);
    const got = j.slice || [];
    const gotById = {};
    got.forEach(m => { gotById[m.id] = m; });

    // 刷新取回段中的已有行内容
    got.forEach(m => {
      const nd = MSGS.node(Number(m.id));
      if (nd){
        const cur = nd.msg;
        if (cur.content !== m.content || cur.author !== m.author){
          nd.msg = m;                 // 原地替换节点承载的消息
          refreshRowNode(m);
        }
      }
    });

    // 服务端已删除：从界面与内存中一起移除
    // 并保持不变量：渲染过的消息内存中必须存在（DOM 与 MSGS 同步删除）。
    const lo = vis.minId, hi = vis.maxId;
    // minId/maxId 是明确范围请求：服务器返回该区间内全部现存。
    // 注意：不在此处 union——pollSlice 只是“视口内刷新”，不获取新区；
    // 真正拉新区的 dir=up/down/around 已在 unionAnchorRange/unionRanges 里把
    // [边界, 锚点] 整体记入（含被删 id）。若此处按 vis 现存范围 union，
    // 反而可能拆碎或漏掉被删 id 所在的可信区间。
    // （删除检测仍走 gotById 差集，见下方）
    Array.from(chat.querySelectorAll('.msg')).forEach(row => {
      const id = Number(row.dataset.id);
      if (id >= lo && id <= hi && !gotById[row.dataset.id]){
        row.remove();
        markQuotesDeleted(id);                       // DOM 里引用该已删消息的也要一并标为不可点
        MSGS.remove(id);
      }
    });
  } finally { busySlice = false; }
}

/* 收集当前与视口相交（可见）的消息行的 id 最小/最大值 */
function visibleSliceInfo(){
  const rect = chat.getBoundingClientRect();
  const chatTop = rect.top, chatBottom = rect.bottom;
  let minId = Infinity, maxId = -1;
  const rows = chat.querySelectorAll('.msg');
  for (const r of rows){
    const b = r.getBoundingClientRect();
    if (b.bottom >= chatTop && b.top <= chatBottom){
      if (r.dataset && r.dataset.id != null){
        const id = Number(r.dataset.id);
        if (!isNaN(id)){ minId = Math.min(minId, id); maxId = Math.max(maxId, id); }
      }
    }
  }
  if (maxId < minId) return null;
  return { minId: minId, maxId: maxId };
}

/* 用一条更新的消息替换对应 DOM 行 */
function refreshRowNode(m){
  const row = chat.querySelector('.msg[data-id="'+CSS.escape(String(m.id))+'"]');
  if (row){
    const wrap = row.querySelector('.content');
    if (wrap) setContentEl(wrap, m.content || '');
  }
}

/* 删除后只移除对应行并保持当前滚动位置，不滚动到底部 */
async function afterDeleteRefresh(){
  if (busyNew && busySlice) return;
  await runPolls();
}

/* “有新消息”下划线标签：有新消息且用户未贴底时显示；点击或用户滚到底即移除。
   用 position:fixed 钉在聊天区可视底部中央（不随消息滚动/加载而移动）。 */
let newMsgEl = null;
function positionNewMsg(){
  if (!newMsgEl) return;
  const r = chat.getBoundingClientRect();   // 聊天区在屏幕上的位置（滚动/加载消息不改变它）
  newMsgEl.style.position = 'fixed';
  newMsgEl.style.left = (r.left + r.width / 2) + 'px';
  newMsgEl.style.transform = 'translateX(-50%)';
  newMsgEl.style.bottom = Math.max(8, window.innerHeight - r.bottom + 8) + 'px';
  newMsgEl.style.zIndex = '55';
  newMsgEl.style.margin = '0 auto';
  newMsgEl.style.alignSelf = 'auto';
}
function showNewMsgLabel(){
  if (newMsgEl) return;
  newMsgEl = document.createElement('button');
  newMsgEl.className = 'newMsg';
  newMsgEl.textContent = '有新消息';
  newMsgEl.addEventListener('click', function(){
    // 跳到最新消息区域（用局部片段加载），确保视口是连续且已拉取的区段
    jumpToMessage(String(latestId || 1));
    hideNewMsgLabel();
  });
  document.body.appendChild(newMsgEl);
  positionNewMsg();
}
function hideNewMsgLabel(){
  if (newMsgEl){ newMsgEl.remove(); newMsgEl = null; }
}
/* 统一清除规则：任何时候，只要 bottomId() 在 DOM 且已滚动到底部（isAtBottom()），
   就清除“有新消息”标识。所有清除入口都走这里，避免散落的无条件 hide。 */
function hideNewMsgIfBottom(){
  if (isAtBottom()) hideNewMsgLabel();
}
window.addEventListener('resize', function(){ positionNewMsg(); });

/* 向上翻片：分两步。
   第 1 步【确保 MSGS 有更早数据】：目标上方区段若已全部在 fetchedRanges（已拉过），
      则不再发请求（数据已在 MSGS）；否则发请求把更早的段并入 MSGS。
   第 2 步【无论如何，从 MSGS 补 DOM】：只要前置(MSGS 有更早消息)完成，就无条件把
      MSGS 中“id < 当前 DOM 最小 id”的旧消息按升序插到列表顶部——不依赖服务器返回。
   这样请求成功、或数据已在缓存，都能把内容补进 DOM。 */
function fetchOlder(){
  // 独立 busy 防重入：被 send/下翻占用也不影响上翻发起请求。
  tryFetchOlder();
}
function currentDomMinId(){
  let m = null;
  Array.from(chat.querySelectorAll('.msg')).forEach(function(r){
    const n = Number(r && r.dataset && r.dataset.id);
    if (!isNaN(n) && (m == null || n < m)) m = n;
  });
  return m;
}
/* 以 anchor 为锚向更旧 fetch 一批并入 MSGS；返回“服务器实际返回的条数”。
   同 doFetchDown：不能以“新增 MSGS 条数”判断——返回的消息可能早已在缓存里，
   用 added=0 会误判“没拉到”而停止重数，导致可信区段外第一条恰被删时第一次上翻翻不动。 */
async function doFetchUp(anchor){
  let gotN = 0;
  try {
    const res = await fetch(api('api/messages?dir=up&min=' + CHUNK + '&anchor=' + anchor));
    const j = await res.json();
    if (j && j.ok){
      totalCount = j.count;
      noteLatest(j.slice);
      noteFirstId(j);
      gotN = (j.slice || []).length;
      (j.slice || []).forEach(function(m){
        if (MSGS.findIndex(x => String(x.id) === String(m.id)) < 0) MSGS.push(m);
      });
      if (gotN){
        unionAnchorRange(anchor, j.slice.map(m => Number(m.id)), 'up');
        (j.slice||[]).forEach(m => { if (m.seq < topSeq) topSeq = m.seq; });
      }
    }
  } catch(e){}
  return gotN;
}
/* 从 MSGS 往“更旧”方向数最多 CHUNK 条现存消息，供上翻渲染。
   按【规则】：先尽量数出 CHUNK 条现存 MSGS 消息；再检查这组是否跨越未 fetch 区段。
   返回 { msgs, crossedGap, boundary }：
     - msgs      数出的现存消息（升序）
     - crossedGap 这组里有任一 id 未 covered（即跨越未 fetch 区段）→ 应 fetch
     - boundary   数出的条数 < CHUNK 且不是因为 gap，而是 MSGS 里已没有更旧（总消息边界）
   调用方据此决定：crossedGap → fetch 分支；否则（且非 boundary 或 boundary）→ 渲染。 */
function countOlder(domMin){
  const msgs = [];
  let crossedGap = false;
  // 走步：从“id < domMin 的最大现存”沿 prev 指针向左走 CHUNK 步。
  // 每步检查【上一节点 → 当前节点/边界】之间间隔是否整体 covered：
  // 删除空洞已 fetch 则信任；未 fetch 间隔立即停，不跨。
  let cur = MSGS.predBefore(domMin);
  if (cur && cur !== MSGS.head){
    // 起点间隔：从首节点后到边界 domMin-1
    if (!rangeFullyCovered(cur.id + 1, domMin - 1)){ crossedGap = true; }
  }
  while (msgs.length < CHUNK && cur && cur !== MSGS.head && !crossedGap){
    msgs.unshift(cur.msg);                 // 先收当前节点（最旧一条的 prev 是 head，也要收）
    const pv = cur.prev;
    if (pv === MSGS.head) break;           // 已到最旧一条，停
    if (!rangeFullyCovered(pv.id + 1, cur.id - 1)){ crossedGap = true; break; }
    cur = pv;
  }
  // 走完后整体检查【从最小 id 到边界 domMin-1】是否被 fetchedRanges 包含
  if (msgs.length){
    const ids = msgs.map(m => Number(m.id));
    const lo = Math.min.apply(null, ids);
    if (!rangeFullyCovered(lo, domMin - 1)) crossedGap = true;
  }
  // boundary = 已数完还 < CHUNK，且未因 gap 止步
  const boundary = msgs.length < CHUNK && !crossedGap;
  return { msgs, crossedGap, boundary };
}
/* 渲染已定稿的旧消息片（countOlder 后调用），并返回新增条数。 */
function renderOlder(msgs){
  if (!msgs || !msgs.length) return 0;
  const anchor = firstVisibleMessage();
  const anchorOffset = anchor ? anchor.offsetTop : null;
  const scrollBefore = chat.scrollTop;
  const oldTop = chat.firstElementChild;
  const addedIds = [];
  msgs.forEach(function(m){
    if (chat.querySelector('.msg[data-id="' + CSS.escape(String(m.id)) + '"]')) return;
    const row = buildMessage(m);
    chat.insertBefore(row, oldTop);
    if (Number(m.seq) < topSeq) topSeq = m.seq;
    addedIds.push(Number(m.id));
  });
  // 注：这里不再 union —— 可信区段在 fetch 时已由 unionAnchorRange 记录为
  // [边界, 锚点] 整体（含删除），渲染只是把已 covered 的现存放进 DOM；
  // 若按“实际渲染的现存 id”union 会把被删 id 排除、把区间拆碎。
  updateOlderButton();
  trimDomAround(anchor);
  if (anchor && anchor.isConnected){
    chat.scrollTop = anchor.offsetTop - (anchorOffset - scrollBefore);
  } else {
    const delta = chat.scrollHeight - chat.scrollTop - scrollBefore;
    chat.scrollTop += Math.max(0, delta);
  }
  return addedIds.length;
}
/* 上翻主流程（按规则）：先数 12 条现存；若跨越未 fetch → fetch 分支(拉一批)再重数；
   否则（边界更少也可以）渲染。用独立 busyOlder 防重入，不被 send/下翻阻塞。 */
async function tryFetchOlder(){
  if (!ME) return;
  if (busyOlder) return;                 // 自身正在上翻，防重入
  const domMin = currentDomMinId();
  if (domMin == null) return;
  const { msgs, crossedGap } = countOlder(domMin);
  // 已到顶：当前 DOM 最旧一条 <= 全局最小现存 id → 不再向上，渲染已能给的（可少于 12 条）。
  const atBoundary = globalEarliestId > 0 && domMin <= globalEarliestId;
  // 不跨越，且（已满 CHUNK 或已到顶）→ 直接渲染（到顶可少于 12 条）。
  if (!crossedGap && (msgs.length >= CHUNK || atBoundary)){ renderOlder(msgs); return; }
  // 跨越未 fetch，或没满 CHUNK → fetch 分支。
  busyOlder = true;
  const added = await doFetchUp(domMin);   // 以当前最旧现存为锚，往更旧拉一批
  busyOlder = false;
  if (added <= 0){ // 服务器已无更旧（确为总边界）
    renderOlder(msgs.filter(m => coveredById(Number(m.id)))); return;
  }
  // 重数并渲染：若仍跨越，只渲染已 covered 的子集，绝不把跨区的消息渲进 DOM
  const r = countOlder(currentDomMinId() || domMin);
  if (r.crossedGap){ renderOlder(r.msgs.filter(m => coveredById(Number(m.id)))); return; }
  renderOlder(r.msgs);
}

/* 是否还有更近(更新)的消息可向下加载：已加载的最新 seq 还没到服务端最新 */
function hasMoreNewer(){ return bottomSeq < (totalCount - 1); }

/* 向下翻片：与 fetchOlder 一致，分两步。
   第 1 步【确保 MSGS 有更近数据】：下方区段若已全部拉过则不发请求（数据已在缓存）；
      已到最新也无更多；否则请求把更近的段并入 MSGS。
   第 2 步【无论如何，从 MSGS 补 DOM】：从 MSGS 把“id > 当前 DOM 最大 id”的消息按升序
      追加到列表末尾——不依赖本次服务器返回，缓存命中也能补。
   注意：MSGS 只是缓存，缓存里有 ≠ DOM 里有；渲染始终单独从 MSGS 取。 */
function fetchNewer(){
  // 与 fetchOlder 一致：独立 busyNewer 防重入，不被 send/上翻阻塞。
  tryFetchNewer();
}
/* 下翻主流程（对称于 tryFetchOlder）：先数 12 条现存；若跨越未 fetch → fetch 分支再重数；
   否则渲染（边界更少也可以）。 */
async function tryFetchNewer(){
  if (!ME) return;
  if (busyNewer) return;                 // 自身正在下翻，防重入
  const domMax = currentDomMaxId();
  if (domMax == null) return;
  const { msgs, crossedGap } = countNewer(domMax);
  if (!crossedGap){ renderNewer(msgs); return; }
  // 跨越未 fetch → fetch 分支
  busyNewer = true;
  const added = await doFetchDown(domMax);
  busyNewer = false;
  if (added <= 0){ renderNewer(msgs.filter(m => coveredById(Number(m.id)))); return; }
  const r = countNewer(currentDomMaxId() || domMax);
  // 重数后仍跨越：只渲染已 covered 的子集，绝不把跨区的消息渲进 DOM
  if (r.crossedGap){ renderNewer(r.msgs.filter(m => coveredById(Number(m.id)))); return; }
  renderNewer(r.msgs);
}
function currentDomMaxId(){
  let m = null;
  Array.from(chat.querySelectorAll('.msg')).forEach(function(r){
    const n = Number(r && r.dataset && r.dataset.id);
    if (!isNaN(n) && (m == null || n > m)) m = n;
  });
  return m;
}
/* 以 anchor 为锚向更新 fetch 一批并入 MSGS；返回“服务器实际返回的条数”。
   注意不能以“新增 MSGS 条数”判断：若返回的消息早已在缓存里(如 pollNew 收过但区间未记)，
   added=0 会误判“没拉到”而停止重数，导致可信区段外第一条恰被删时第一次下翻翻不动。 */
async function doFetchDown(anchor){
  let gotN = 0;
  try {
    const res = await fetch(api('api/messages?dir=down&min=' + CHUNK + '&anchor=' + anchor));
    const j = await res.json();
    if (j && j.ok){
      totalCount = j.count;
      noteLatest(j.slice);
      noteFirstId(j);
      gotN = (j.slice || []).length;
      (j.slice || []).forEach(m => {
        if (MSGS.findIndex(x => String(x.id) === String(m.id)) < 0) MSGS.push(m);
      });
      if (gotN) unionAnchorRange(anchor, j.slice.map(m => Number(m.id)), 'down');
    }
  } catch(e){}
  return gotN;
}
/* 从 MSGS 往“更新”方向数最多 CHUNK 条现存消息（对称于 countOlder）。
   返回 { msgs, crossedGap, boundary }；boundary = 少于 CHUNK 且非 gap（总边界）。
   逻辑与原逐 id 扫描一致：从 domMax+1 逐 id 递增，遇未 covered 空洞立即停（被删的 covered
   id 跳过但不跨越）。仅用跳表 node(id) 代替原来的 byId 杂凑做 O(log n) 查找，不改变查询语义。 */
function countNewer(domMax){
  const msgs = [];
  let crossedGap = false;
  // 走步：从“id > domMax 的最小现存”沿 next 指针向右走 CHUNK 步。
  // 每步检查【边界/当前节点 → 下一节点】之间间隔是否整体 covered：
  // 删除空洞已 fetch 则信任；未 fetch 间隔立即停，不跨。
  let cur = MSGS.succAfter(domMax);
  if (cur && cur !== MSGS.tail){
    // 起点间隔：从边界 domMax+1 到首节点前
    if (!rangeFullyCovered(domMax + 1, cur.id - 1)){ crossedGap = true; }
  }
  while (msgs.length < CHUNK && cur && cur !== MSGS.tail && !crossedGap){
    msgs.push(cur.msg);                    // 先收当前节点（最后一条的 next 是 tail，也要收）
    const nx = cur.next;
    if (nx === MSGS.tail) break;           // 已到最后一条，停
    if (!rangeFullyCovered(cur.id + 1, nx.id - 1)){ crossedGap = true; break; }
    cur = nx;
  }
  // 走完后整体检查【从边界 domMax+1 到最大 id】是否被 fetchedRanges 包含
  if (msgs.length){
    const ids = msgs.map(m => Number(m.id));
    const hi = Math.max.apply(null, ids);
    if (!rangeFullyCovered(domMax + 1, hi)) crossedGap = true;
  }
  const boundary = msgs.length < CHUNK && !crossedGap;
  return { msgs, crossedGap, boundary };
}
/* 渲染已定稿的新消息片（countNewer 后调用），返回新增条数。 */
function renderNewer(msgs){
  if (!msgs || !msgs.length) return 0;
  let added = 0;
  const addedIds = [];
  msgs.forEach(function(m){
    if (chat.querySelector('.msg[data-id="' + CSS.escape(String(m.id)) + '"]')) return;
    if (MSGS.findIndex(x => String(x.id) === String(m.id)) < 0) MSGS.push(m);
    bottomSeq = m.seq;
    appendMessageNode(m);
    addedIds.push(Number(m.id));
    added++;
  });
  if (added > 0){
    // 注：不再 union —— 可信区段在 fetch 时已由 unionAnchorRange 记录为整体（含删除）；
    // 按渲染的现存 id union 会把被删 id 排除、把区间拆碎。
    if (isAtBottom()) hideNewMsgIfBottom();
  }
  trimDomAround(firstVisibleMessage());
  return added;
}

/* 裁掉 DOM 中距离“当前位置(锚点行)”过远的消息：锚点上方、下方各最多保留 DOM_LIMIT(=100) 条。
   只移除 DOM 行(不删 MSGS 缓存、不动 fetchedRanges)，防止翻片后 DOM 无限膨胀。 */
var DOM_LIMIT = 100;
function trimDomAround(anchorEl){
  if (!chat) return;
  const rows = Array.from(chat.querySelectorAll('.msg'));
  if (rows.length <= DOM_LIMIT * 2 + 1) return;
  // 中心：锚点行优先，否则取可视第一条，再否则中部行
  let center = -1;
  if (anchorEl && anchorEl.isConnected){
    center = rows.indexOf(anchorEl);
  }
  if (center < 0){
    const vis = firstVisibleMessage();
    if (vis) center = rows.indexOf(vis);
  }
  if (center < 0) center = Math.floor(rows.length / 2);
  const remove = [];
  // 上方超过 DOM_LIMIT 条的行（center 之前的第 101 条再往上）
  for (let i = center - DOM_LIMIT - 1; i >= 0; i--) remove.push(rows[i]);
  // 下方超过 DOM_LIMIT 条的行（center 之后的第 101 条再往下）
  for (let i = center + DOM_LIMIT + 1; i < rows.length; i++) remove.push(rows[i]);
  remove.forEach(function(r){
    if (r && r.parentNode) r.parentNode.removeChild(r);
  });
}

/* 找到聊天区域内首个“下边界可见”的消息，作为滚动锚点 */
function firstVisibleMessage(){
  const rect = chat.getBoundingClientRect();
  const chatTop = rect.top, chatBottom = rect.bottom;
  const rows = chat.querySelectorAll('.msg');
  for (const r of rows){
    const b = r.getBoundingClientRect();
    if (b.bottom >= chatTop && b.top <= chatBottom){
      return r;
    }
  }
  return null;
}

/* 取当前视口边缘消息的 id：
   dir=up   -> 视口内“最上面”那条消息的 id（向上翻片锚点）
   dir=down -> 视口内“最下面”那条消息的 id（向下翻片锚点） */
function viewportEdgeId(dir){
  const rect = chat.getBoundingClientRect();
  const chatTop = rect.top, chatBottom = rect.bottom;
  let top = null, bottom = null;
  const rows = chat.querySelectorAll('.msg');
  for (const r of rows){
    const b = r.getBoundingClientRect();
    if (b.bottom >= chatTop && b.top <= chatBottom){
      if (!top || b.top < top.rect.top){ top = { rect: b, id: r.dataset.id }; }
      if (!bottom || b.bottom > bottom.rect.bottom){ bottom = { rect: b, id: r.dataset.id }; }
    }
  }
  const pick = dir === 'up' ? top : bottom;
  return pick ? Number(pick.id) : null;
}

/* ---------- 发送 ---------- */
async function send(){
  if (!ME || !ME.canSend) return;
  const editor = document.getElementById('editor');
  const content = editor.value.trim();
  if (!content){ if (!isTouchDevice()) focusEditor(); return; }
  const btn = document.getElementById('sendBtn');
  btn.disabled = true;
  try {
    const res = await fetch(api('api/send'), {
      method:'POST', headers:{'Content-Type':'application/json'},
      body: JSON.stringify({ content: content })
    });
    const j = await res.json();
    if (!j.ok){ openModal('发送失败', (j.error || '未知错误'), null); }
    else {
      editor.value = '';
      clearDraft();                          // 发送成功后清掉草稿，重载不会复活已发内容
      updateCharCount();
      renderPreview();                       // 清空预览
      // 用服务端返回的这条消息，把“自己的新消息”渲染到列表尾部（不弹“有新消息”）。
      // 与收到新消息(pollNew)处理完全一致：
      //   1) 任何处置前，按当前 DOM 现状判定次新（最后一个没被删过/已渲染的最大 id）是否在 DOM；
      //   2) 次新在 DOM → 插入新行；
      //   3) 插入且插入前在底部 → 滚到底；
      //   4) 无论如何都放入 MSGS。
      const m = j && j.msg;
      if (m && m.id != null && !MSGS.some(x => String(x.id) === String(m.id))){
        // —— 处置前的 DOM 现状判定（读取、比较均不能受下面 push 影响）——
        const gapBefore = chat.scrollHeight - chat.scrollTop - chat.clientHeight;
        // 次新= bottomId()（MSGS 记录的最后一个没被删过的消息）。用它和 DOM 核对：
        // 不能用 DOM 现存最大 id（它永远在 DOM 里、恒 true）。bottomId() 在 DOM 说明尾部连续。
        const prevBottomId = bottomId();
        const bottomInDom = (prevBottomId == null || prevBottomId <= 0) ||
          !!(chat.querySelector('.msg[data-id="' + CSS.escape(String(prevBottomId)) + '"]'));
        const wasAtBottomBefore = bottomInDom && gapBefore <= 5;
        // —— 处置 ——
        MSGS.push(m);
        markQuotesAlive(m.id);                       // 自己的新消息现存：引用恢复可引用
        unionRanges(Number(m.id), Number(m.id));   // 自己的新消息也是“已知/已fetch”，记入已拉取区段
        bottomSeq = m.seq;
        const mid = Number(m.id);
        if (mid > latestId) latestId = mid;
        if (typeof j.count === 'number'){ totalCount = j.count; lastSeenCount = j.count; }
        if (bottomInDom){
          appendMessageNode(m);
          if (wasAtBottomBefore){ requestAnimationFrame(stickBottom); hideNewMsgIfBottom(); }
        }
      }
      // 仍触发一次轮询，让服务端整体态尽快与本地一致
      runPolls();
    }
  } finally { btn.disabled = false; if (!isTouchDevice()) focusEditor(); }
}

/* ---------- 删除 ---------- */
function confirmDelete(id){
  openModal('删除消息', '确定删除这条消息吗？', function(){
    if (busy) return;
    fetch(api('api/delete?id=' + id), {method:'POST'})
      .then(r => r.json())
      .then(j => {
        if (j && j.ok){
          // 删除成功后立即重建视图，归正游标
          afterDeleteRefresh();
        } else {
          alert((j && j.error) || '删除失败');
        }
      })
      .catch(() => alert('删除失败，请重试'));
  });
}


/* ================================================================
 *  编辑器（猎户座工具栏）
 *  - 插入后把光标放到合理位置：行内包裹->包裹内容内末尾；行前缀->下一选择点；
 *    颜色->用取色器选好点“确定”再插入；有序列表->自动按行递增序号。
 * ================================================================ */
const COLOR_PRESETS = ['#e74c3c','#e67e22','#f1c40f','#2ecc71','#1abc9c','#3498db','#2e86de','#9b59b6','#34495e','#444444','#000000','#ffffff'];
let pendingColor = null;         // 取色器确定后要执行的回调

const TOOLS = [
  {t:'粗体',  act:w=>wrap(w,'**','**')},
  {t:'斜体',  act:w=>wrap(w,'*','*')},
  {t:'删除线',act:w=>wrap(w,'~~','~~')},
  {t:'下划线',act:w=>wrap(w,'[u]','[/u]')},
  {t:'高亮',  act:w=>wrap(w,'<mark>','</mark>')},
  {t:'行内码',act:w=>wrap(w,'`','`')},
  {t:'一级',  act:w=>headingPrefix(w,1)},
  {t:'二级',  act:w=>headingPrefix(w,2)},
  {t:'三级',  act:w=>headingPrefix(w,3)},
  {t:'引用',  act:w=>blockPrefix(w, function(){ return '> '; }, true)},
  {t:'无序',  act:w=>blockPrefix(w, function(){ return '- '; })},
  {t:'有序',  act:w=>blockPrefixOrdered(w)},
  {t:'代码块',act:w=>blockFence(w)},
  {t:'分隔线',act:w=>insertHr(w)},
  {t:'表格',  act:w=>insertTable(w)},
  {t:'链接',  act:w=>insertLink(w)},
  {t:'剧透',  act:w=>wrap(w,'[spoiler]','[/spoiler]')},
  {t:'折叠',  act:w=>insertDetails(w)},
  {t:'脚注',  act:w=>wrap(w,'^[',']')},
  {t:'滚动盒',act:w=>insertScrollable(w)},
  {t:'非滚动盒',act:w=>insertPlainDiv(w)},
  {t:'图片',  act:w=>insertImage(w)},
  {t:'上传',  act:w=>openUpload(w)},
  {t:'颜色',  act:w=>openColorPicker()},
  {t:'引用消息',act:w=>insertQuoteTool(w)},
];

function getSel(w){ return w.value.substring(w.selectionStart, w.selectionEnd); }

/* 行内包裹：有选中则包裹并在内容末尾停光标；
   无选中则插入“开标记+闭标记”并在两个标记之间停光标（光标落在标签内部，便于继续输入内容）。 */
function wrap(w, b, e){
  const v=w.value, s=w.selectionStart, en=w.selectionEnd, sel=v.slice(s,en);
  if (sel){
    w.setRangeText(b+sel+e, s, en, 'start');
    w.setSelectionRange(s+b.length, s+b.length+sel.length); // 光标停在内容末尾
  } else {
    w.setRangeText(b+e, s, en, 'start');
    w.setSelectionRange(s+b.length, s+b.length);            // 光标在两个标记之间
  }
  w.focus(); renderPreview(); updateCharCount();
}

/* 标题：行首加 #；若本行已是同级标题则去掉（切换） */
function headingPrefix(w, n){
  const v=w.value, s=w.selectionStart;
  const ls=v.lastIndexOf('\n', s-1)+1;
  const mark='#'.repeat(n)+' ';
  const cur=v.slice(ls, ls+mark.length);
  let newText, selStart;
  if (cur===mark){ newText=v.slice(0,ls)+v.slice(ls+mark.length); selStart=ls; }
  else { newText=v.slice(0,ls)+mark+v.slice(ls); selStart=ls+mark.length; }
  w.value=newText; w.setSelectionRange(selStart, selStart);
  w.focus(); renderPreview(); updateCharCount();
}

/* 块级前缀（引用/列表）：给选中行加前缀（有序列表按行递增序号）。
   引用(prefixEmpty=true)会给空行也加前缀，方便空行起引用。
   插入后光标停在该行前缀之后，紧贴内容开头，便于继续输入。 */
function blockPrefix(w, prefixFn, prefixEmpty){
  const v=w.value;
  const s=Math.min(w.selectionStart,w.selectionEnd), en=Math.max(w.selectionStart,w.selectionEnd);
  const start=v.lastIndexOf('\n', s-1)+1;
  let end=v.indexOf('\n', en); if(end<0)end=v.length;
  const lines=v.slice(start,end).split('\n');
  let idx=1; let changed=false; let cursorOffset=0; let haveCursor=false;
  const BLOCK=/^(\s*)(?:>\s+|[-*+]\s+|\d+\.\s+)/;   // 已带块符号
  const newLines=lines.map((l, li)=>{
    if (!l.trim() && !prefixEmpty) return l;       // 默认跳过空行；引用则给空行加前缀
    const p=prefixFn(idx, l, idx); idx++;
    changed=true;
    let out;
    const m=BLOCK.exec(l);
    if (m){ out = m[1] + p + l.slice(m[0].length); }
    else   { out = p + l; }
    if (!haveCursor && changed){ cursorOffset = out.length - l.length; haveCursor=true; } // 首行新增前缀长度
    return out;
  });
  if (!changed){ w.focus(); return; }
  const replacement=newLines.join('\n');
  w.setRangeText(replacement, start, end, 'start');
  w.setSelectionRange(start + cursorOffset, start + cursorOffset); // 光标在前缀之后
  w.focus(); renderPreview(); updateCharCount();
}
function blockPrefixOrdered(w){ return blockPrefix(w, function(i, ln, idx){ return idx+'. '; }); }

/* 代码块：插入围栏，光标落到围栏内空行处 */
function blockFence(w){
  const v=w.value, s=w.selectionStart, en=w.selectionEnd, sel=v.slice(s,en);
  const lang='';
  const open='```'+lang+'\n', close='\n```\n';
  let text, caret;
  if (sel){ text=open+sel.replace(/^/gm,'')+close; caret=s+open.length; }
  else { text=open+close; caret=s+open.length; }
  w.setRangeText(text, s, en, 'start');
  w.setSelectionRange(caret, caret);
  w.focus(); renderPreview(); updateCharCount();
}
function append(s){ return {s:'',e:'',replace:s}; }

/* 分隔线：始终保证分隔线所在行与上一非空内容之间恰好留一个空行，
   避免 markdown 把 “文字\n---” 误判成 setext 标题；光标落在分隔线后一行行首。 */
function insertHr(w){
  const v=w.value, s=w.selectionStart, en=w.selectionEnd;
  const before = v.slice(0, s);
  // 去除前文末尾的空白/换行，再补一个空行，保证“正文\n\n---”结构
  let trimmed = before.replace(/[ \t]*\n+$/,'');
  let inset = trimmed + '\n\n---\n' + v.slice(en);
  w.setRangeText(inset, 0, w.value.length, 'start');
  const caret = trimmed.length + 2 + 4;     // 正文 + 空行 + ---\n
  w.setSelectionRange(caret, caret);
  w.focus(); renderPreview(); updateCharCount();
}

/* 表格：插入后可快速跳到第一个空单元格 */
function insertTable(w){
  const v=w.value, s=w.selectionStart, en=w.selectionEnd;
  const t='\n| 列1 | 列2 | 列3 |\n| --- | --- | --- |\n|  |  |  |\n';
  w.setRangeText(t, s, en, 'start');
  const caret=s+t.length-1;               // 最后一个单元
  w.setSelectionRange(caret, caret);
  w.focus(); renderPreview(); updateCharCount();
}

/* 链接：插入后光标落在线 URL 上，方便直接输入 */
function insertLink(w){
  const v=w.value, s=w.selectionStart, en=w.selectionEnd, sel=v.slice(s,en);
  const text=sel || '链接文字';
  const t='['+text+'](https://)';
  w.setRangeText(t, s, en, 'start');
  // https:// 起始位置：'['(1) + text + ']'(1) + '('(1) = s+text.length+3；选中这 8 个字符
  const urlStart = s + 1 + text.length + 1 + 1;
  w.setSelectionRange(urlStart, urlStart + 8);
  w.focus(); renderPreview(); updateCharCount();
}

/* 图片：插入 ![描述](URL)，先选中 URL 便于直接粘贴地址 */
function insertImage(w){
  const v=w.value, s=w.selectionStart, en=w.selectionEnd, sel=v.slice(s,en);
  const alt=sel || '图片';
  const t='!['+alt+'](https://)';
  w.setRangeText(t, s, en, 'start');
  // URL 起始：'!['(2) + alt + ']'(1) + '('(1) = s+alt.length+4；选中 8 字符
  const urlStart = s + 2 + alt.length + 1 + 1;
  w.setSelectionRange(urlStart, urlStart + 8);
  w.focus(); renderPreview(); updateCharCount();
}

/* 折叠块 */
function insertDetails(w){
  const v=w.value, s=w.selectionStart, en=w.selectionEnd, sel=v.slice(s,en)||'内容';
  const t='[details="点击展开"]\n'+sel+'\n[/details]';
  w.setRangeText(t, s, en, 'start');
  w.setSelectionRange(s+'[details="点击展开"]'.length+1, s+'[details="点击展开"]'.length+1+(sel.length));
  w.focus(); renderPreview(); updateCharCount();
}
/* 滚动盒 */
function insertScrollable(w){
  const v=w.value, s=w.selectionStart, en=w.selectionEnd, sel=v.slice(s,en)||'';
  const t='<div data-theme-scrollable="true">\n\n'+sel+'\n\n</div>';
  w.setRangeText(t, s, en, 'start');
  w.setSelectionRange(s+'<div data-theme-scrollable="true">'.length+2, s+'<div data-theme-scrollable="true">'.length+2+sel.length);
  w.focus(); renderPreview(); updateCharCount();
}
/* 非滚动盒：普通 div（无滚动容器属性；带 orion-plain-box 类便于显示为盒状容器） */
function insertPlainDiv(w){
  const v=w.value, s=w.selectionStart, en=w.selectionEnd, sel=v.slice(s,en)||'';
  const t='<div class="orion-plain-box">\n\n'+sel+'\n\n</div>';
  w.setRangeText(t, s, en, 'start');
  w.setSelectionRange(s+'<div class="orion-plain-box">'.length+2, s+'<div class="orion-plain-box">'.length+2+sel.length);
  w.focus(); renderPreview(); updateCharCount();
}

/* 颜色选择器 */
function openColorPicker(){
  const cp=document.getElementById('colorPicker');
  const preview=document.getElementById('previewPane').querySelector('.preview');
  // 记录当前选中内容，确定后插入
  const editor=document.getElementById('editor');
  const s=editor.selectionStart, en=editor.selectionEnd, sel=editor.value.slice(s,en);
  pendingColor={ s, en, sel };
  // 定位弹层到“颜色”按钮附近（用固定位置：编辑器上方居中）
  cp.style.left=(window.innerWidth/2-110)+'px';
  cp.style.top=(computeColorTop())+'px';
  cp.removeAttribute('hidden');              // 去掉 hidden 属性，配合 [open] 显示
  cp.setAttribute('open','');
  document.getElementById('colorValue').focus();
}
function computeColorTop(){
  const area=document.getElementById('editorArea').getBoundingClientRect();
  return Math.max(40, area.top-160);
}
function closeColorPicker(){
  const cp=document.getElementById('colorPicker');
  cp.setAttribute('hidden','');
  cp.removeAttribute('open');
  pendingColor=null;
}
function confirmColor(){
  if (!pendingColor) return;
  const editor=document.getElementById('editor');
  const hex=document.getElementById('colorValue').value;
  const { s, en, sel }=pendingColor;
  const inner=sel || '文字';
  const t='[color='+hex+']'+inner+'[/color]';
  editor.setRangeText(t, s, en, 'start');
  editor.setSelectionRange(s+('[color='+hex+']').length, s+('[color='+hex+']').length+inner.length);
  editor.focus(); renderPreview(); updateCharCount();
  closeColorPicker();
}
function buildColorPicker(){
  const presets=document.getElementById('colorPresets');
  COLOR_PRESETS.forEach(hex=>{
    const b=document.createElement('button');
    b.style.background=hex;
    b.setAttribute('aria-label','颜色 '+hex);
    b.addEventListener('click', ()=>{ document.getElementById('colorValue').value=hex; });
    presets.appendChild(b);
  });
  document.getElementById('colorPickerOk').addEventListener('click', confirmColor);
  document.getElementById('colorPickerCancel').addEventListener('click', closeColorPicker);
  document.getElementById('colorPickerClose').addEventListener('click', closeColorPicker);
}

/* 实时预览（防抖 300ms） */
let previewTimer=null;

/* ================================================================
 *  文件上传：拖拽到按钮 / 点击选文件；走进度条；≤10MB；完成后返回地址
 * ================================================================ */
function openUpload(){
  if (!ME || !ME.canSend){ return; }
  document.getElementById('uploadModal').classList.add('on');
  document.getElementById('uploadProgress').style.display='none';
  document.getElementById('uploadResult').style.display='none';
  document.getElementById('dropZone').textContent='点击选择 / 拖拽文件到这里（≤10MB）';
  setProgress(0);
  // 清空旧选择结果
  document.getElementById('uploadUrl').textContent='';
  // 重置完成按钮与标题：本次面板里上传成功前不可点击（防止把上次/无效地址插入编辑器）
  const done=document.getElementById('uploadDone');
  done.disabled=true;
  delete done.dataset.lastUrl;
  document.getElementById('uploadTitle').textContent='上传文件';
}
function closeUpload(){
  document.getElementById('uploadModal').classList.remove('on');
}
function setProgress(pct){
  const bar=document.getElementById('progressBar');
  if (bar) bar.style.width=pct+'%';
}
function startUploadFile(file){
  if (!file) return;
  document.getElementById('uploadDone').disabled=true;   // 上传中不可点“完成”
  document.getElementById('uploadProgress').style.display='block';
  document.getElementById('uploadResult').style.display='none';
  document.getElementById('uploadTitle').textContent='上传中：'+file.name;
  setProgress(0);
  const fdata=new FormData();
  fdata.append('file', file);
  const xhr=new XMLHttpRequest();
  xhr.open('POST', api('api/upload'));
  xhr.upload.onprogress=function(e){
    if (e.lengthComputable){ setProgress(Math.round(e.loaded/e.total*100)); }
  };
  xhr.onload=function(){
    let j=null;
    try { j=JSON.parse(xhr.responseText); } catch(err){ }
    if (xhr.status>=200 && xhr.status<300 && j && j.ok){
      setProgress(100);
      document.getElementById('uploadResult').style.display='block';
      document.getElementById('uploadUrl').textContent=j.url;
      document.getElementById('uploadTitle').textContent='上传完成';
      document.getElementById('dropZone').textContent='上传成功：'+file.name;
      document.getElementById('uploadDone').dataset.lastUrl=j.url;
      document.getElementById('uploadDone').disabled=false;   // 上传成功后才可点“完成”
    } else {
      document.getElementById('uploadTitle').textContent='上传失败';
      document.getElementById('dropZone').textContent=(j&&j.error)||'上传失败，请重试';
      setProgress(0);
      const done=document.getElementById('uploadDone');
      done.disabled=true;
      delete done.dataset.lastUrl;
    }
  };
  xhr.onerror=function(){
    document.getElementById('uploadTitle').textContent='上传失败';
    document.getElementById('dropZone').textContent='网络错误，请重试';
    setProgress(0);
    const done=document.getElementById('uploadDone');
    done.disabled=true;
    delete done.dataset.lastUrl;
  };
  xhr.send(fdata);
}
function buildUpload(){
  const dz=document.getElementById('dropZone');
  const input=document.getElementById('fileInput');
  dz.addEventListener('click', ()=>{ input.value=''; input.click(); });
  dz.addEventListener('dragover', (e)=>{ e.preventDefault(); dz.classList.add('drag'); });
  dz.addEventListener('dragleave', ()=>{ dz.classList.remove('drag'); });
  dz.addEventListener('drop', (e)=>{
    e.preventDefault(); dz.classList.remove('drag');
    const f=(e.dataTransfer&&e.dataTransfer.files&&e.dataTransfer.files[0])||null;
    if (f){ checkAndUpload(f); }
  });
  input.addEventListener('change', ()=>{ checkAndUpload(input.files[0]); });
  document.getElementById('uploadCancel').addEventListener('click', closeUpload);
  document.getElementById('uploadDone').addEventListener('click', ()=>{
    const url=document.getElementById('uploadDone').dataset.lastUrl;
    if (url){
      // 把地址插入编辑器当前光标处
      const w=document.getElementById('editor');
      const s=w.selectionStart, en=w.selectionEnd;
      w.setRangeText(url, s, en, 'end');
      w.focus(); renderPreview(); updateCharCount();
    }
    closeUpload();
  });
  function checkAndUpload(file){
    if (!file) return;
    if (file.size > 10*1024*1024){ document.getElementById('uploadTitle').textContent='文件过大'; document.getElementById('dropZone').textContent='文件超过 10MB 上限'; return; }
    startUploadFile(file);
  }
}
function renderPreview(){
  const src=document.getElementById('editor').value;
  if (previewTimer) clearTimeout(previewTimer);
  previewTimer=setTimeout(()=>{
    const out=document.getElementById('previewOut');
    out.innerHTML='';
    if (!src.trim()){ out.innerHTML=''; return; }
    out.appendChild(renderRich(src));
  }, 300);
}

function applyTool(tool){
  const w=document.getElementById('editor');
  const res = tool.act(w);
  if (res && res.replace != null){ w.setRangeText(res.replace, w.selectionStart, w.selectionEnd, 'end'); }
  w.focus(); renderPreview(); updateCharCount();
}

function buildToolbar(){
  const bar=document.getElementById('toolbar');
  TOOLS.forEach((t,i)=>{
    if (t.sep){ const d=document.createElement('div'); d.className='sep'; bar.appendChild(d); return; }
    const b=document.createElement('button');
    b.textContent=t.t;
    b.addEventListener('click',()=>applyTool(t));
    bar.appendChild(b);
  });
  buildColorPicker();
  buildUpload();
}

function updateCharCount(){
  const v=document.getElementById('editor').value;
  document.getElementById('statusText').textContent = v.length + ' 字 · ' + (ME ? ME.name : '');
}

/* ---------- 弹窗 ---------- */
function openModal(title, body, onOk){
  document.getElementById('modalTitle').textContent = title;
  document.getElementById('modalBody').textContent = body;
  document.getElementById('modal').classList.add('on');
  const ok=document.getElementById('modalOk'), cancel=document.getElementById('modalCancel');
  const noop=()=>{};
  cancel.onclick = function(){ closeModal(); };
  ok.onclick = function(){ closeModal(); if(onOk) onOk(); };
}
function closeModal(){ document.getElementById('modal').classList.remove('on'); }

/* ---------- 黑夜模式 ---------- */
let darkMode = false;
try { darkMode = localStorage.getItem('darkMode') === '1'; } catch(e){}
function applyDarkMode(on){
  darkMode = on;
  document.body.classList.toggle('dark', on);
  try { localStorage.setItem('darkMode', on ? '1' : '0'); } catch(e){}
  const sw = document.getElementById('darkSwitch');
  if (sw){ sw.textContent = on ? '开' : '关'; sw.classList.toggle('on', on); sw.setAttribute('aria-pressed', on ? 'true' : 'false'); }
}
function toggleDark(){ applyDarkMode(!darkMode); }
window.applyDarkMode = applyDarkMode;
window.toggleDark = toggleDark;

/* ---------- 通知设置 ---------- */
let notifEnabled = false;
try { notifEnabled = localStorage.getItem('notifEnabled') === '1'; } catch(e){}

function openSettings(){ document.getElementById('settingsModal').classList.add('on'); refreshNotifUI(); applyDarkMode(darkMode); refreshCacheSize(); }
function closeSettings(){ document.getElementById('settingsModal').classList.remove('on'); }
// 主脚本整体包在 IIFE 内，内联 onclick 需要全局函数，故显式挂到 window
window.openSettings = openSettings;
window.closeSettings = closeSettings;

/* ---------- 字体（IndexedDB 本地存储） ----------
   选择服务器字体或下载后都存入 IndexedDB；应用时从 IDB 取 Blob 生成 ObjectURL 注册 @font-face，
   设为 CSS 变量 --font 的名族。此后每次打开都从 IDB 读，不再从服务器下载。清除即从 IDB 删除并恢复默认。 */
const IDB_DB = 'sf_fonts', IDB_STORE = 'fonts', FONT_ID = 'activeFont';
function idb(){ return new Promise(function(res, rej){
  try {
    const rq = indexedDB.open(IDB_DB, 1);
    rq.onupgradeneeded = function(){ const db = rq.result; if (!db.objectStoreNames.contains(IDB_STORE)) db.createObjectStore(IDB_STORE, { keyPath: 'file' }); };
    rq.onsuccess = function(){ res(rq.result); };
    rq.onerror = function(){ rej(rq.error); };
  } catch(e){ rej(e); }
});}
function idbGetAll(db){ return new Promise(function(res, rej){ try { const tx=db.transaction(IDB_STORE,'readonly'); const q=tx.objectStore(IDB_STORE).getAll(); q.onsuccess=()=>res(q.result||[]); q.onerror=()=>rej(q.error); } catch(e){ rej(e); } }); }
function idbPut(db, rec){ return new Promise(function(res, rej){ try { const tx=db.transaction(IDB_STORE,'readwrite'); tx.objectStore(IDB_STORE).put(rec); tx.oncomplete=()=>res(); tx.onerror=()=>rej(tx.error); } catch(e){ rej(e); } }); }
function idbDel(db, file){ return new Promise(function(res, rej){ try { const tx=db.transaction(IDB_STORE,'readwrite'); tx.objectStore(IDB_STORE).delete(file); tx.oncomplete=()=>res(); tx.onerror=()=>rej(tx.error); } catch(e){ rej(e); } }); }
let _activeFontUrl = null;                 // 已注册的字体 ObjectURL（防止泄漏）
const DEFAULT_FONT_FAMILY = "'LXGW WenKai TC','KaiTi','Noto Serif SC',serif";
/* 用一个已持有的 Blob 注册 @font-face 并应用。返回 CSS 名族。 */
function applyFontBlob(blob, mime, familyName){
  const family = familyName || ('sf_' + Date.now());
  const url = URL.createObjectURL(blob);
  let css = "@font-face{font-family:'" + family + "';src:url('" + url + "') format('truetype');font-weight:normal;}";
  if ((mime||'').indexOf('woff') >= 0) css = "@font-face{font-family:'" + family + "';src:url('" + url + "');font-weight:normal;}";
  const style = document.createElement('style');
  style.setAttribute('data-sf-font', family);
  style.textContent = css;
  document.head.appendChild(style);
  if (_activeFontUrl) try { URL.revokeObjectURL(_activeFontUrl); } catch(e){}
  _activeFontUrl = url;
  try { localStorage.setItem('sfFontActive', family); } catch(e){}
  applyFontFamily(family);
  return family;
}
function applyFontFamily(family){
  const el = document.body;
  el.style.setProperty('--font', family + "," + DEFAULT_FONT_FAMILY);
}
function setFontStatus(txt){ const el = document.getElementById('fontStatus'); if (el) el.textContent = txt || ''; }
/* 打开字体子面板并刷新列表 */
async function openFontPanel(){
  document.getElementById('fontModal').classList.add('on');
  await refreshFontPanel();
}
function closeFontPanel(){ document.getElementById('fontModal').classList.remove('on'); }

/* —— 单槽位 —— 本地只有一个字体槽位：有则用之，无则默认。 */
async function getFontSlot(){
  const db = await idb().catch(function(e){ console.error('[字体] 打开 IndexedDB 失败:', e); return null; });
  if (!db) return null;
  try { const all = await idbGetAll(db); return all[0] || null; } catch(e){ console.error('[字体] 读取槽位失败:', e); return null; }
}
/* 把给定字体写进槽位（替换旧槽位）并应用。 */
async function putFontSlot(f){
  const db = await idb().catch(function(e){ console.error('[字体] 打开 IndexedDB 失败:', e); return null; });
  if (!db) return false;
  // 单槽位：先清空再写入
  try { const all = await idbGetAll(db); await Promise.all(all.map(r => idbDel(db, r.file))); } catch(e){ console.error('[字体] 清理旧槽位失败:', e); }
  try { await idbPut(db, { file: f.file, name: f.name, size: f.size, mime: f.mime, blob: f.blob }); }
  catch(e){ console.error('[字体] 写入槽位失败（文件大小=' + f.size + '）:', e); return false; }
  applyFontBlob(f.blob, f.mime, 'local_' + f.name);
  return true;
}
/* 清空槽位 → 恢复默认。 */
async function clearFontSlot(){
  const db = await idb().catch(function(e){ console.error('[字体] 打开 IndexedDB 失败:', e); return null; });
  if (db){ try { const all = await idbGetAll(db); await Promise.all(all.map(r => idbDel(db, r.file))); } catch(e){ console.error('[字体] 清空槽位失败:', e); } }
  resetFontStyle();
  refreshFontPanel();
  setFontStatus('已清除本地字体，恢复默认');
}
function resetFontStyle(){
  try { localStorage.removeItem('sfFontActive'); } catch(e){}
  document.querySelectorAll('style[data-sf-font]').forEach(function(s){ s.remove(); });
  if (_activeFontUrl){ try { URL.revokeObjectURL(_activeFontUrl); } catch(e){} _activeFontUrl = null; }
  document.body.style.setProperty('--font', DEFAULT_FONT_FAMILY);
}
/* 启动时：若槽位有字体则应用（IndexedDB，不再下载）。 */
async function restoreActiveFont(){
  try { const f = await getFontSlot(); if (f && f.blob){ applyFontBlob(f.blob, f.mime, 'local_' + f.name); } } catch(e){}
}
/* 刷新子面板：当前使用 + 清除/导出按钮可用态 + 服务器字体列表。 */
async function refreshFontPanel(){
  // 当前使用 + 清除/导出按钮
  const cur = document.getElementById('fontCurrent');
  const clearBtn = document.getElementById('fontClearBtn');
  const exportBtn = document.getElementById('fontExportBtn');
  const f = await getFontSlot().catch(()=>null);
  if (cur){
    if (f && f.name) cur.textContent = f.name;
    else cur.textContent = '默认';
  }
  const has = !!(f && f.blob);
  if (clearBtn){ clearBtn.disabled = !has; }
  if (exportBtn){ exportBtn.disabled = !has; }
  // 服务器列表
  await refreshFontServerList();
}
/* 导出槽位字体：把 IndexedDB 里存的字体 Blob 下载到本地。 */
async function exportSlotFont(){
  try {
    const f = await getFontSlot().catch(()=>null);
    if (!f || !f.blob){ setFontStatus('槽位为空'); return; }
    const url = URL.createObjectURL(f.blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = f.file || (f.name + '.ttf');
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    setTimeout(function(){ URL.revokeObjectURL(url); }, 4000);
    console.log('[字体] 导出槽位字体:', f.file || f.name);
  } catch(e){ console.error('[字体] 导出失败:', e); setFontStatus('导出失败'); }
}
async function refreshFontServerList(){
  const box = document.getElementById('fontServerList');
  if (!box) return;
  box.innerHTML = '<span class="fontEmpty">加载中…</span>';
  try {
    const res = await fetch(api('fonts'));
    const j = await res.json();
    box.innerHTML = '';
    if (!j.ok || !j.fonts || !j.fonts.length){ box.innerHTML = '<span class="fontEmpty">服务器暂无可选字体</span>'; return; }
    j.fonts.forEach(function(ff){
      const it = document.createElement('div'); it.className = 'fontItem';
      const rowTop = document.createElement('div'); rowTop.style.cssText = 'display:flex;align-items:center;justify-content:space-between;gap:8px;width:100%';
      const name = document.createElement('span'); name.className = 'fiName'; name.textContent = ff.name;
      const meta = document.createElement('span'); meta.className = 'fiMeta'; meta.textContent = fmtSize(ff.size);
      const acts = document.createElement('span'); acts.className = 'fiActs';
      const dl = document.createElement('button'); dl.className = 'fontDl'; dl.textContent = '下载并使用';
      dl.dataset.file = ff.file;
      dl.addEventListener('click', ()=>{ onFontDlClick(ff, dl); });
      acts.appendChild(dl);
      rowTop.appendChild(name); rowTop.appendChild(meta); rowTop.appendChild(acts);
      it.appendChild(rowTop);
      box.appendChild(it);
    });
  } catch(e){ box.innerHTML = '<span class="fontEmpty">加载失败</span>'; }
}
/* 当前正在进行的下载（取消用）。 */
let _fontAbort = null;       // AbortController
let _fontActiveBtn = null;   // 当前下载行的“下载并使用”按钮
function onFontDlClick(f, btn){
  if (_fontActiveBtn === btn && _fontAbort){ _fontAbort.abort(); return; }   // 取消
  downloadFontToIDB(f, btn);
}
async function downloadFontToIDB(f, btn){
  if (_fontActiveBtn) return;              // 已有下载进行中，忽略其它
  // 锁定所有下载按钮；当前按钮变“取消”
  const allBtns = Array.from(document.querySelectorAll('#fontServerList .fontDl'));
  _fontActiveBtn = btn;
  allBtns.forEach(function(b){ b.disabled = true; });
  btn.disabled = false; btn.textContent = '取消';

  const bar = document.getElementById('fontProgress');
  const fill = bar.querySelector('i');
  const text = document.getElementById('fontProgressText');
  const url = 'fonts/' + encodeURIComponent(f.file);
  const abort = (_fontAbort = new AbortController());

  function setProgress(pct, label){
    fill.style.width = Math.min(100, Math.max(0, pct)) + '%';
    if (text) text.textContent = label;
  }
  function showBar(on){ bar.classList.toggle('on', on); }
  function doneBar(){
    _fontAbort = null; _fontActiveBtn = null;
    showBar(false); fill.style.width = '0';
    allBtns.forEach(function(b){ b.disabled = false; b.textContent = '下载并使用'; });
  }

  try {
    setFontStatus('获取大小：' + f.name + '…');
    showBar(true); setProgress(0, '连接…');
    // 先 HEAD 拿总大小（进度条必须先知道总量）
    let total = 0;
    try {
      const head = await fetch(url, { method: 'HEAD', signal: abort.signal, credentials: 'same-origin' });
      if (head.ok){
        total = parseInt(head.headers.get('Content-Length') || '0', 10) || 0;
      } else { console.error('[字体下载] HEAD 非2xx status=', head.status, 'file=', f.file); }
    } catch(e){ if (abort.signal.aborted) throw { __cancel: true }; console.error('[字体下载] HEAD 失败:', e, 'file=', f.file); }
    // GET 下载
    const res = await fetch(url, { signal: abort.signal, credentials: 'same-origin' });
    if (!res.ok){ console.error('[字体下载] GET 非2xx status=', res.status, 'file=', f.file, 'url=', res.url); throw { __http: true }; }
    const reader = res.body.getReader();
    const chunks = [];
    let received = 0;
    while (true){
      const r = await reader.read();
      if (r.done) break;
      chunks.push(r.value);
      received += r.value.length;
      const pct = total ? (received / total * 100) : -1;
      if (pct >= 0) setProgress(pct, '下载中：' + f.name + ' ' + Math.floor(pct) + '%');
      else setProgress(-1, '下载中：' + f.name + ' ' + fmtSize(received));
    }
    const blob = new Blob(chunks, { type: (res.headers.get('Content-Type') || 'application/octet-stream') });
    setProgress(100, '已下载');
    const ok = await putFontSlot({ file: f.file, name: f.name, size: f.size, mime: f.mime, blob: blob });
    if (!ok){ console.error('[字体下载] 存入 IndexedDB 失败 file=', f.file); throw { __store: true }; }
    console.log('[字体下载] 成功存储并使用字体:', f.file, 'size=', f.size);
    setFontStatus('已下载并使用：' + f.name);
    doneBar();
    refreshFontPanel();
  } catch(e){
    if (e && e.__cancel){
      console.log('[字体下载] 已取消下载:', f.file);
      setFontStatus('已取消');
    } else {
      console.error('[字体下载] 下载异常 file=', f.file, 'url=', url, 'pageUrl=', location.href, 'err=', e);
      setFontStatus('下载失败');
    }
    doneBar();
  }
}
/* 从本地磁盘选择字体文件放入槽位。 */
function pickLocalFont(){
  const input = document.getElementById('fontLocalInput');
  if (!input) return;
  input.value = '';
  input.click();
}
function bindLocalFontInput(){
  const input = document.getElementById('fontLocalInput');
  const label = document.getElementById('fontLocalPickName');
  if (!input) return;
  input.addEventListener('change', async function(){
    const file = input.files && input.files[0];
    if (!file){ return; }
    const ext = (file.name.split('.').pop() || '').toLowerCase();
    const okExt = ['ttf','otf','woff','woff2','eot'];
    if (okExt.indexOf(ext) < 0){
      if (label) label.textContent = '不支持的后缀：.' + ext;
      setFontStatus('仅支持字体文件(.ttf/.otf/.woff/.woff2)');
      return;
    }
    if (label) label.textContent = file.name + ' ' + fmtSize(file.size);
    setFontStatus('存储中：' + file.name + '…');
    const mime = ext === 'woff2' ? 'font/woff2'
               : ext === 'woff' ? 'font/woff'
               : ext === 'ttf' ? 'font/ttf'
               : 'font/otf';
    const ok = await putFontSlot({ file: file.name, name: file.name.replace(/\.[^.]+$/, ''), size: file.size, mime: mime, blob: file });
    if (!ok){ setFontStatus('保存失败'); return; }
    console.log('[字体] 本地已放入槽位并应用:', file.name);
    setFontStatus('已应用本地字体：' + file.name);
    refreshFontPanel();
  });
}
function fmtSize(n){
  if (n == null) return '';
  if (n >= 1048576) return (n/1048576).toFixed(1) + ' MB';
  if (n >= 1024) return (n/1024).toFixed(0) + ' KB';
  return n + ' B';
}
window.openFontPanel = openFontPanel;
window.closeFontPanel = closeFontPanel;
window.clearFontSlot = clearFontSlot;
window.exportSlotFont = exportSlotFont;
window.pickLocalFont = pickLocalFont;
bindLocalFontInput();          // 绑定“从本地选择字体”的文件输入监听
/* 退出登录 */
window.doLogout = doLogout;
function doLogout(){ logout(); }
/* 缓存大小：估算 MSGS 暂用的字节数（近似：把每条消息的文本长度累加，含少量头部开销）。 */
function cacheSizeBytes(){
  if (!MSGS) return 0;
  let sum = 0, n = 0;
  MSGS.forEach(function(m){
    n++;
    sum += ((m && m.content ? m.content.length : 0)
          + (m && m.author ? m.author.length : 0) + 24);   // +24 每条约头部/对象开销
  });
  return { bytes: sum, count: n };
}
function refreshCacheSize(){
  const el = document.getElementById('cacheSize');
  if (!el || !MSGS) return;
  const { bytes, count } = cacheSizeBytes();
  const kb = bytes / 1024;
  el.textContent = count + ' 条 · ' + (kb >= 1024 ? (kb/1024).toFixed(1)+' MB' : kb.toFixed(1)+' KB');
}
window.refreshCacheSize = refreshCacheSize;
/* 清除缓存：
   - DOM 裁剪永远是「正负 1 CHUNK」：只保留当前片(锚点上下各 CHUNK)。
   - 最下 3×CHUNK：只是不从跳表移除，并在设置 fetchedRanges 时并入；不参与 DOM 裁剪。
   流程：先把 DOM 裁剪到当前片；再把跳表截成「当前片 ∪ 最下3CHUNK」；最后设 fetchedRanges
   为「当前片 ∪ 最下3CHUNK」的区间。 */
function clearMessageCache(){
  if (!chat || !MSGS) return;
  // —— 当前片（视口上下共 2 CHUNK，用于 DOM 裁剪）——
  // 走步：从锚点的跳表节点沿 prev/next 指针各走 CHUNK 步，收集现存消息。
  // 但**不跨越未 fetch 的空洞**：每步检查“上一步所在 id ↔ 下一节点”之间间隔是否整体
  // covered，遇未覆盖即停——否则从 116 走 next 会跨过 [328,610] 未 fetch 区到 611..942，
  // 把中间不可信区间也并入（视口区间可信、末尾区间可信、二者之间不可信）。
  const curMap = new Map();
  const anchor = firstVisibleMessage();
  if (anchor){
    const anchorNode = MSGS.node(Number(anchor.dataset.id));
    if (anchorNode){
      curMap.set(anchorNode.id, anchorNode.msg);
      // 右：沿 next 走 CHUNK 步，间隔未 covered 即停
      let lastId = anchorNode.id;
      let x = anchorNode.next;
      let n = 0;
      while (x && x !== MSGS.tail && n < CHUNK){
        if (!rangeFullyCovered(lastId + 1, x.id - 1)) break;
        curMap.set(x.id, x.msg);
        lastId = x.id;
        x = x.next; n++;
      }
      // 左：沿 prev 走 CHUNK 步，间隔未 covered 即停
      let firstId = anchorNode.id;
      x = anchorNode.prev;
      n = 0;
      while (x && x !== MSGS.head && n < CHUNK){
        if (!rangeFullyCovered(x.id + 1, firstId - 1)) break;
        curMap.set(x.id, x.msg);
        firstId = x.id;
        x = x.prev; n++;
      }
    } else {
      // 锚点消息不在跳表（可能刚被删除/清出）：退而求其次，以跳表末端为“当前片”中心
      const mn = MSGS.maxNode();
      if (mn){
        curMap.set(mn.id, mn.msg);
        let x = mn.prev;
        let n = 0;
        while (x && x !== MSGS.head && n < CHUNK){
          if (!rangeFullyCovered(x.id + 1, mn.id - 1)) break;
          curMap.set(x.id, x.msg);
          x = x.prev; n++;
        }
      }
    }
  }
  const curKeep = Array.from(curMap.values());
  // —— 最下 3×CHUNK（仅并入缓存与区间，不参与 DOM）——
  // 从末尾最大 id 往前数 3×CHUNK 条现存，但**不跨越未 fetch 的空洞**：
  // 若末端删除密集，数 3×CHUNK 条现存会越过 610 之类从未 fetch 的区间，把未可信区也 union 进来。
  // 走步时每步检查“上一节点 → 当前节点”之间间隔是否整体 covered，遇未覆盖即停。
  const bottomKeep = [];
  var bn = MSGS.maxNode();
  while (bn && bn !== MSGS.head && bottomKeep.length < CHUNK * 3){
    const pv = bn.prev;
    if (pv !== MSGS.head && !rangeFullyCovered(pv.id + 1, bn.id - 1)) break;  // 间隔未覆盖：停
    bottomKeep.push(bn.msg);
    bn = pv;
  }
  // —— 合并缓存保留集 = 当前片 ∪ 最下3CHUNK ——
  const keepMap = new Map();
  curKeep.forEach(m => keepMap.set(Number(m.id), m));
  bottomKeep.forEach(m => keepMap.set(Number(m.id), m));
  const keep = Array.from(keepMap.values());
  // 1) DOM 裁剪：只留「当前片」的行，其余移除（正负 1 CHUNK）
  const domKeepIds = new Set(curKeep.map(m => String(m.id)));
  Array.from(chat.querySelectorAll('.msg')).forEach(function(row){
    if (!domKeepIds.has(String(row.dataset.id))) row.remove();
  });
  // 2) 跳表截片：保留 当前片 ∪ 最下3CHUNK
  const newMSGS = makeSkipList();
  keep.forEach(m => newMSGS.add(JSON.parse(JSON.stringify(m))));
  MSGS = newMSGS;
  // 3) 设 fetchedRanges = 当前片区间 ∪ 最下3CHUNK 区间
  fetchedRanges = [];
  if (curKeep.length){
    unionRanges(Math.min.apply(null, curKeep.map(m => Number(m.id))),
                Math.max.apply(null, curKeep.map(m => Number(m.id))));
  }
  if (bottomKeep.length){
    unionRanges(Math.min.apply(null, bottomKeep.map(m => Number(m.id))),
                Math.max.apply(null, bottomKeep.map(m => Number(m.id))));
  }
  // 更新游标
  topSeq = keep.length ? Math.min.apply(null, keep.map(m=>Number(m.seq))) : Infinity;
  bottomSeq = keep.length ? Math.max.apply(null, keep.map(m=>Number(m.seq))) : -1;
  refreshCacheSize();
}
window.clearMessageCache = clearMessageCache;
// 应用黑夜模式（页面加载即生效）
applyDarkMode(darkMode);

function refreshNotifUI(){
  const grant = document.getElementById('notifGrant');
  const sw = document.getElementById('notifSwitch');
  const status = document.getElementById('notifStatus');
  const unsupported = typeof Notification === 'undefined';
  if (unsupported){
    grant.disabled = true; grant.textContent = '不支持';
    sw.disabled = true; sw.classList.remove('on'); sw.textContent = '关';
    status.textContent = '当前浏览器不支持桌面通知';
    return;
  }
  const perm = Notification.permission;
  if (perm === 'denied'){
    grant.disabled = true; grant.textContent = '已拒绝';
    sw.disabled = true; sw.classList.remove('on'); sw.textContent = '关';
    status.textContent = '通知已被拒绝，请在浏览器设置中允许本站通知';
    return;
  }
  if (perm === 'granted'){
    grant.disabled = true; grant.textContent = '已授权';
    sw.disabled = false;
    sw.classList.toggle('on', notifEnabled);
    sw.textContent = notifEnabled ? '开' : '关';
    sw.setAttribute('aria-pressed', notifEnabled ? 'true' : 'false');
    status.textContent = '';
    return;
  }
  // default（未决定）
  grant.disabled = false; grant.textContent = '请求授权';
  sw.disabled = true; sw.classList.remove('on'); sw.textContent = '关';
  status.textContent = '授权后可开启通知';
}
window.refreshNotifUI = refreshNotifUI;

function requestNotification(){
  if (typeof Notification === 'undefined'){ openModal('提示', '当前浏览器不支持桌面通知', null); return; }
  Notification.requestPermission().then(function(perm){
    if (perm === 'granted'){
      notifEnabled = true;
      try { localStorage.setItem('notifEnabled', '1'); } catch(e){}
    }
    refreshNotifUI();
  });
}
window.requestNotification = requestNotification;

function toggleNotifSwitch(){
  if (typeof Notification === 'undefined' || Notification.permission !== 'granted') return;
  notifEnabled = !notifEnabled;
  try { localStorage.setItem('notifEnabled', notifEnabled ? '1' : '0'); } catch(e){}
  refreshNotifUI();
}
window.toggleNotifSwitch = toggleNotifSwitch;

/* 收到新的“他人”消息且未在看当前页时，弹系统通知 */
function notifyNewMessage(author, preview){
  try {
    if (typeof Notification === 'undefined' || Notification.permission !== 'granted' || !notifEnabled) return;
    // 页面可见且有焦点时无需打扰；切到后台/其他标签才弹
    if (document.hidden || !document.hasFocus()){
      const n = new Notification('欣欣聊天室：' + author + ' 发来新消息', { body: preview || '', silent: false });
      n.onclick = function(){ window.focus(); n.close(); };
    }
  } catch(e){}
}

/* ---------- 导出聊天记录 ---------- */
/* 完整时间戳：YYYY-MM-DD HH:MM:SS */
function fmtTimeFull(ts){
  const d = new Date(ts * 1000);
  const p = n => String(n).padStart(2, '0');
  return d.getFullYear() + '-' + p(d.getMonth() + 1) + '-' + p(d.getDate())
       + ' ' + p(d.getHours()) + ':' + p(d.getMinutes()) + ':' + p(d.getSeconds());
}
/* 触发浏览器下载一个纯文本文件 */
function downloadFile(text, name){
  const blob = new Blob([text], { type: 'text/plain;charset=utf-8' });
  const url = URL.createObjectURL(blob);
  const a = document.createElement('a');
  a.href = url; a.download = name;
  document.body.appendChild(a);
  a.click();
  document.body.removeChild(a);
  setTimeout(function(){ URL.revokeObjectURL(url); }, 1500);
}
/* 导出：按最小/最大 id 拉取消息（留空=不限）并下载为纯文本 */
async function exportChat(){
  const minEl = document.getElementById('exportMin');
  const maxEl = document.getElementById('exportMax');
  const st = document.getElementById('exportStatus');
  let lo = parseInt(minEl.value, 10);
  let hi = parseInt(maxEl.value, 10);
  if (isNaN(lo)) lo = null;
  if (isNaN(hi)) hi = null;
  if (lo != null && hi != null && lo > hi){ const t = lo; lo = hi; hi = t; }
  if (lo != null && lo < 1) lo = 1;
  if (hi != null && hi < 1) hi = null;
  const lo2 = (lo != null) ? lo : 1;
  const hi2 = (hi != null) ? hi : 2147483647;
  st.textContent = '正在获取消息…';
  try {
    const res = await fetch(api('messages?minId=' + lo2 + '&maxId=' + hi2));
    const j = await res.json();
    if (!j.ok) throw new Error(j.error || '获取失败');
    const msgs = j.slice || [];
    if (!msgs.length){ st.textContent = '该 id 区间没有消息'; return; }
    const parts = msgs.map(function(m){
      // 全部信息 + 原始猎户座语法（BBCode/markdown 原样保留，不剥记号）
      return '[#' + m.id + '] ' + (m.author || '?') + '\n'
           + fmtTimeFull(m.time) + '\n'
           + String(m.content || '');
    });
    const head = '欣欣聊天室 聊天记录\n导出范围：id ' + lo2 + ' ~ ' + hi2 + '（共 ' + msgs.length + ' 条）\n';
    const text = head + '\n' + parts.join('\n\n' + '―'.repeat(32) + '\n\n') + '\n';
    downloadFile(text, 'chat_' + lo2 + '-' + hi2 + '.txt');
    st.textContent = '已导出 ' + msgs.length + ' 条';
  } catch(e){
    st.textContent = '导出失败：' + (e && e.message ? e.message : e);
  }
}
window.exportChat = exportChat;
/* ---------- 交互动画帮助（可选） ---------- */

/* 编辑器草稿（localStorage 自动保存）：
   每 1 秒异步把编辑器内容写入 LS（内容没变则跳过），页面重载后自动恢复，
   避免误刷新丢失正在写的内容。按键名按账户区分，互不串台。 */
function draftKey(){ return 'xch_draft_' + encodeURIComponent((ME && ME.name) || 'guest'); }
function saveDraft(v){ try { localStorage.setItem(draftKey(), v); } catch(e){} }
function clearDraft(){ try { localStorage.removeItem(draftKey()); } catch(e){} }
function loadDraft(){ try { return localStorage.getItem(draftKey()); } catch(e){ return null; } }

/* ---------- 启动 ---------- */
async function init(){
  if (!CODE){ return; }
  computeChunk();                                  // 自适应 CHUNK（四屏幕的最矮消息数量）
  // 锁定移动端显示高度：键盘弹出不改高度（桌面手动缩放仍会重锁）
  pinLayoutHeight();
  window.addEventListener('orientationchange', pinLayoutHeight);
  if (!isTouchDevice()){ window.addEventListener('resize', pinLayoutHeight); }
  const who = document.getElementById('whoTag');
  who.textContent = '当前身份：' + ME.name + (ME.canSend ? ' · 可发言' : ' · 仅阅读');

  buildToolbar();
  const editor = document.getElementById('editor');
  editor.addEventListener('input', () => { updateCharCount(); renderPreview(); });

  // 草稿恢复：重载后从 LS 读出内容填充编辑器
  let lastDraftSaved = null;
  const savedDraft = loadDraft();
  if (savedDraft){
    editor.value = savedDraft;
    lastDraftSaved = savedDraft;
    updateCharCount();
    renderPreview();
  }
  // 草稿自动保存：每 1 秒异步写入 LS
  setInterval(function(){
    const v = editor.value;
    if (v === lastDraftSaved) return;
    lastDraftSaved = v;
    saveDraft(v);
  }, 1000);

  // 编辑器 折叠/展开（默认隐藏，仅可发言账户可见）
  const composer = document.getElementById('composer');
  const toggleBtn = document.getElementById('editorToggleBtn');
  const appRoot = document.getElementById('app');
  const MIN_CHAT_PX = 120;   // 消息区可用像素下限：低于则隐藏消息，让编辑器独占

  /* 实测消息区可用像素：先去掉 editor-full 让聊天回到占位，强制触发一次布局回流后
     量出其真实可用高度；若仍低于下限则隐藏消息、让编辑器压缩放大。
     完全依据 #chat 实际高度判断，而非屏幕像素。 */
  function reflowEditor(){
    requestAnimationFrame(function(){
      if (!chat || !chat.isConnected) return;
      appRoot.classList.remove('editor-full');
      // 强制同步布局，确保上一条样式变更已生效；再推迟一拍实际测量
      void chat.offsetHeight;
      setTimeout(function(){
        const h = chat.offsetHeight || 0;
        appRoot.classList.toggle('editor-full', h < MIN_CHAT_PX);
      }, 30);
    });
  }
  function applyEditorOpen(open){
    composer.classList.toggle('open', open);
    appRoot.classList.toggle('editor-open', open);
    toggleBtn.textContent = open ? '收起编辑器' : '写消息';
    if (open){ reflowEditor(); } else { appRoot.classList.remove('editor-full'); }
    // 编辑器占用空间改变了聊天区高度 → 重新定位“有新消息”标识
    requestAnimationFrame(positionNewMsg);
  }
  toggleBtn.addEventListener('click', function(){
    applyEditorOpen(!composer.classList.contains('open'));
    if (composer.classList.contains('open')){ renderPreview(); }
    // 手机上打开编辑器不自动聚焦，避免弹出键盘遮住界面
    if (composer.classList.contains('open') && !isTouchDevice()){ focusEditor(); }
  });
  // 窗口尺寸变化时，若编辑器开着则重新实测消息区高度
  window.addEventListener('resize', function(){
    if (composer.classList.contains('open')) reflowEditor();
  });
  if (!ME.canSend){ toggleBtn.style.display='none'; }
  else { applyEditorOpen(false); }         // 默认隐藏
  // 发送后收起编辑器
  document.getElementById('sendBtn').addEventListener('click', function(){ send(); });
  document.getElementById('toBottomBtn').addEventListener('click', goToBottom);
  editor.addEventListener('keydown', e=>{ if((e.ctrlKey||e.metaKey)&&e.key==='Enter'){e.preventDefault();send();} });

  // 滚动事件：维护 atBottom；滚到顶自动向上翻片；滚到底(未到最新末尾)自动向下翻片；贴底则移除“有新消息”
  chat.addEventListener('scroll', function(){
    atBottom = isAtBottom();                 // bottomId 在 DOM 且距底<5px
    if (atBottom) hideNewMsgIfBottom();      // 到底即清“有新消息”（随意滚动时也生效）
    updateOlderButton();                       // 顶部是否显示“加载中…”
    // 滚到顶/底边缘时请求翻片；fetchOlder/fetchNewer 内部会校验“未拉取区段”才真正拉取，
    // 且渲染 MSGS 这一步不因共享 busy 而跳过（busy 时仍会把已完整区段渲进 DOM）。
    if (chat.scrollTop <= 40){ fetchOlder(); }
    const gap = chat.scrollHeight - chat.scrollTop - chat.clientHeight;
    if (gap <= 60){ fetchNewer(); }
  });

  // 全局搜索：输入防抖搜索，点击结果跳转并高亮
  initSearch();

  updateCharCount();
  await fullReload();
  // 启动轮询：新消息（用全局最大id）与视口刷新各自独立、持续运行；分片翻页由滚动触发
  pollTimer = setInterval(pollNew, POLL_MS);        // 新消息轮询（永不停止）
  viewportTimer = setInterval(pollSlice, POLL_MS);  // 视口内分片刷新
  document.getElementById('app').classList.add('on');
  focusEditor();
}

/* 搜索：防抖查询 /api/search 并展示结果，点击跳到对应消息区域 */
let searchTimer = null;
function initSearch(){
  const input = document.getElementById('searchInput');
  const box = document.getElementById('searchResults');
  if (!input) return;
  input.addEventListener('input', function(){
    clearTimeout(searchTimer);
    const q = input.value.trim();
    if (!q){ box.hidden = true; box.innerHTML=''; return; }
    searchTimer = setTimeout(function(){ runSearch(q); }, 300);
  });
  input.addEventListener('keydown', function(e){
    if (e.key === 'Enter'){ clearTimeout(searchTimer); runSearch(input.value.trim()); }
    if (e.key === 'Escape'){ box.hidden = true; }
  });
  document.addEventListener('click', function(e){
    if (!e.target.closest('#topSearch')) box.hidden = true;
  });
}
function runSearch(q){
  const box = document.getElementById('searchResults');
  if (!box) return;
  fetch(api('search?q=' + encodeURIComponent(q)))
    .then(r => r.json())
    .then(j => {
      box.innerHTML = '';
      if (!j || !j.ok) return;
      if (!j.results || !j.results.length){
        box.innerHTML = '<div class="srE">无结果</div>';
        box.hidden = false;
        return;
      }
      window.__allSearchResults = j.results || [];
      j.results.forEach(function(res){
        const b = document.createElement('button');
        b.className = 'sr';
        b.type = 'button';
        const t = document.createElement('span');
        // 服务端已在命中词前后插入 \u0003…\u0004，这里转义后替换成 <mark> 高亮命中片段
        t.textContent = '';
        let snipHtml = esc(res.snippet || '(空)');
        snipHtml = snipHtml.split('\u0003').join('<mark>').split('\u0004').join('</mark>');
        t.innerHTML = snipHtml;
        const m = document.createElement('span');
        m.className = 'srM';
        m.textContent = '#' + res.id + ' · ' + res.author + ' · ' + fmtTime(res.time);
        b.appendChild(t); b.appendChild(m);
        b.addEventListener('click', function(){
          box.hidden = true;
          const inp = document.getElementById('searchInput');
          if (inp) inp.blur();
          jumpToMessage(String(res.id));
        });
        box.appendChild(b);
      });
      // 底部显示命中总数，明确展示的是“全部匹配”而非截断后的子集
      const total = j.results.length;
      const footer = document.createElement('div');
      footer.className = 'srE';
      footer.textContent = '共命中 ' + total + ' 条消息';
      box.appendChild(footer);
      box.hidden = false;
    })
    .catch(function(){ box.innerHTML = '<div class="srE">搜索失败</div>'; box.hidden = false; });
}

/* ---------- 登录 ---------- */
window.__initApp = async function(code, name, canSend){
  CODE = code; ME = {name:name, canSend:canSend};
  document.getElementById('login').style.display = 'none';
  document.getElementById('app').style.display = 'flex';
  await init();
};

window.logout = function(){
  if (pollTimer) clearInterval(pollTimer);
  if (viewportTimer) clearInterval(viewportTimer);
  sessionStorage.removeItem('xch_code');
  sessionStorage.removeItem('xch_name');
  // 清掉地址栏 get 参数 p，回到无凭据的登录页，避免刷新后自动重登同一账户
  const clean = location.origin + location.pathname;
  location.replace(clean);
};

/* ---------- 登录 ----------
   登录凭证即地址栏 get 参数 p，这也是判断登录的唯一依据。
   若地址栏没有 p，则弹出密码框；提交后服务端校验通过，把凭证写到 get 参数再载入。
   移除地址栏的 p 即登出，不依赖 sessionStorage（避免“删了参数仍登录”）。 */
document.addEventListener('DOMContentLoaded', function(){
  // 字体不受登录影响：只要 IndexedDB 槽位有字体就取出应用（即使在登录页/屏蔽页）
  restoreActiveFont().catch(function(){});
  // Safari 临时屏蔽：屏蔽页已由内联脚本显示，这里不再进入任何登录/启动流程
  if (window.__safariBlocked) return;
  const p = new URLSearchParams(location.search).get('p');
  // 登录状态只看 get 参数，不看 sessionStorage
  sessionStorage.removeItem('xch_code');
  const code = p;

  if (code){
    startWith(code);
    return;
  }
  // 未登录：显示登录框，等待输入密码
  document.getElementById('loginForm').addEventListener('submit', onLoginSubmit);
});

function onLoginSubmit(e){
  e.preventDefault();
  const pass = document.getElementById('pass').value.trim();
  const err = document.getElementById('loginErr');
  if (!pass){ err.textContent = '请输入密码'; return; }
  err.textContent = '正在验证…';
  fetch('?r=session&p=' + encodeURIComponent(pass))
    .then(r => r.json())
    .then(j => {
      if (j && j.ok){
        // 凭证有效：把凭证写到地址栏 get 参数后重载进入聊天室
        const url = new URL(location.href);
        url.searchParams.set('p', pass);
        // 去掉无关旧参数，仅保留 p，保持地址简洁
        url.search = '?p=' + encodeURIComponent(pass);
        location.replace(url.toString());
      } else {
        err.textContent = (j && j.error) || '密码错误，无法登录';
        document.getElementById('pass').value = '';
        document.getElementById('pass').focus();
      }
    })
    .catch(() => { err.textContent = '连接失败，请重试'; });
}

let starting = false;
async function startWith(code){
  if (starting) return; starting = true;
  try {
    const res = await fetch('?r=session&p=' + encodeURIComponent(code));
    const j = await res.json();
    if (j.ok){
      window.__initApp(code, j.name, j.canSend);
    } else {
      // 凭证无效：清除并回到登录页
      sessionStorage.removeItem('xch_code');
      const url = new URL(location.href);
      url.search = '';
      history.replaceState(null, '', url.toString());
      document.getElementById('login').style.display = 'flex';
      document.getElementById('loginErr').textContent = '登录凭证已失效';
    }
  } catch(e){
      document.getElementById('loginErr').textContent = '连接失败，请检查服务';
  }
}
</script>
</body>
</html>
APPHTML;

/* ---------------- 消息数据读写（SQLite） ---------------- */
const SQLITE_FILE = 'messages.sqlite';

/** 获取数据库连接（惰性初始化；首次建表并迁移旧的 messages.json） */
function db(): PDO {
    static $pdo = null;
    if ($pdo !== null) return $pdo;
    if (!is_dir(DATA_DIR) && !@mkdir(DATA_DIR, 0755, true)) {
        http_response_code(500);
        exit('{"ok":false,"error":"data 目录无法创建"}');
    }
    $file = DATA_DIR . '/' . SQLITE_FILE;
    $created = !is_file($file);
    $pdo = new PDO('sqlite:' . $file);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec('CREATE TABLE IF NOT EXISTS messages (
        id      INTEGER PRIMARY KEY AUTOINCREMENT,
        author  TEXT NOT NULL,
        content TEXT NOT NULL,
        time    INTEGER NOT NULL
    )');
    if ($created) migrateLegacyJson($pdo);
    return $pdo;
}

/** 首次运行时把旧的 messages.json 迁移进 SQLite（保留原 id） */
function migrateLegacyJson(PDO $pdo): void {
    $file = DATA_DIR . '/messages.json';
    if (!is_file($file)) return;
    $data = json_decode((string)@file_get_contents($file), true);
    if (!is_array($data)) return;
    $ins = $pdo->prepare('INSERT OR IGNORE INTO messages (id, author, content, time) VALUES (?,?,?,?)');
    $pdo->beginTransaction();
    try {
        foreach ($data as $m) {
            if (!isset($m['author'], $m['content'])) continue;
            $ins->execute([
                isset($m['id']) ? (int)$m['id'] : null,
                (string)$m['author'],
                (string)$m['content'],
                (int)($m['time'] ?? time()),
            ]);
        }
        $pdo->exec('DELETE FROM sqlite_sequence WHERE name="messages"');
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
    $pdo->commit();
}

/** 插入一条消息，返回含 seq 的完整行 */
function insertMessage(string $author, string $content): array {
    $pdo = db();
    $st = $pdo->prepare('INSERT INTO messages (author, content, time) VALUES (?,?,?)');
    $st->execute([$author, $content, time()]);
    $id = (int)$pdo->lastInsertId();
    $seq = (int)$pdo->query('SELECT COUNT(*) FROM messages WHERE id <= ' . $id)->fetchColumn() - 1;
    return ['id' => $id, 'seq' => $seq, 'author' => $author, 'content' => $content, 'time' => time()];
}

/** 按 id 删除，且仅当作者匹配（账户只能删自己的消息） */
function deleteMessageById(int $id, string $author): bool {
    $pdo = db();
    $st = $pdo->prepare('DELETE FROM messages WHERE id = ? AND author = ?');
    $st->execute([$id, $author]);
    return $st->rowCount() > 0;
}

/** 一条消息在“按 id 排序”列表中的序号（0 起，删除后空洞不计数） */
function seqOf(int $id): int {
    $pdo = db();
    return (int)$pdo->query('SELECT COUNT(*) FROM messages WHERE id <= ' . $id)->fetchColumn() - 1;
}

function jsonExit(array $payload): void {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/* ---------------- 登录校验 ----------------
 * 登录凭证就是密码本身（写在 get 参数 p）。根据密码找到对应账户。
 * 返回 [name, canSend]；找不到则代表未授权。
 */
function findByPassword(?string $pass): ?array {
    if ($pass === null || $pass === '') {
        return null;
    }
    foreach (ORION_ACCOUNTS as $acc) {
        if (hash_equals($acc[1], $pass)) {   // 常量时间比较，避免时序攻击
            return [$acc[0], $acc[2]];       // [显示名, 是否可发消息]
        }
    }
    return null;
}

/** 未授权时输出 JSON 并终止 */
function deny(string $msg = '无访问权限'): void {
    jsonExit(['ok' => false, 'error' => $msg]);
}


/* ---------------- API 路由 ----------------
 * 直接用 $_GET['r'] 区分接口：没有 r（或 r=home）返回用户界面；
 * r=session|messages|content|send|delete|upload 走对应接口。
 * 不再解析 URL 路径，兼容任何部署方式（子目录、/index.php 等）。
 * 上传文件本身仍是站点根目录 uploads/ 下的静态资源，由 Web 服务器直接托管。
 */
$route = 'home';
if (isset($_GET['r']) && is_string($_GET['r']) && $_GET['r'] !== '') {
    $route = strtolower($_GET['r']);
}

try {
switch ($route) {

    case 'home': // 返回单页应用（HTML 见下方大段 __APP_HTML__）
        header('Content-Type: text/html; charset=utf-8');
        // 不缓存，确保前端 JS 每次都拿到最新版本，避免编辑器逻辑更新后仍用旧缓存渲染
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
        header('Expires: 0');
        echo __APP_HTML__;
        exit;

    // GET /api/session?p=<密码>  —— 校验密码并换取账户显示信息
    case 'session':
        $acc = findByPassword($_GET['p'] ?? null);
        if ($acc === null) {
            jsonExit(['ok' => false, 'error' => '密码错误，无法登录']);
        }
        jsonExit(['ok' => true, 'name' => $acc[0], 'canSend' => $acc[1]]);
        break;
    // GET /api/messages?p=<密码>&upto=<last id>&limit=<n>   —— 向上(更老)取一片
    // GET /api/messages?p=<密码>&from=<seq>&limit=<n>        —— 向下(更新)取一片
    // GET /api/messages?p=<密码>&minId=<id>&maxId=<id>       —— 取 id 介于 [minId, maxId] 的所有消息
    // GET /api/messages?p=<密码>&dir=up|down&min=<n>&anchor=<id>
    //   —— 从 anchor(id) 顺着方向取“至少 min 条”现存消息（编号空洞不计入条数）
    case 'messages':
        $acc = findByPassword($_GET['p'] ?? null);
        if ($acc === null) { deny('无访问权限'); }
        $pdo = db();
        $count = (int)$pdo->query('SELECT COUNT(*) FROM messages')->fetchColumn();
        $firstRow = $pdo->query('SELECT MIN(id) FROM messages')->fetchColumn();
        $first = $firstRow === null ? 0 : (int)$firstRow;   // 全局最小现存消息 id（供客户端判断“是否到顶”）
        // CHUNK 由前端按屏幕自适应并随请求传入，服务端不假设固定值，仅留宽松防护上限。
        $limit = max(1, min((int)($_GET['limit'] ?? 200), 500));

        $slice = [];
        if (isset($_GET['dir']) && isset($_GET['anchor'])) {
            $dir = $_GET['dir'] === 'up' ? 'up' : 'down';
            $anchor = (int)$_GET['anchor'];
            $min = max(1, (int)($_GET['min'] ?? 200));
            // SQL 层面过滤：取 id < / > anchor 的现存消息（seq 用关联子查询按全局序号计算）
            $sql = 'SELECT id, author, content, time,'
                 . ' (SELECT COUNT(*) FROM messages m2 WHERE m2.id <= m.id) - 1 AS seq'
                 . ' FROM messages m WHERE m.id ' . ($dir === 'up' ? '<' : '>') . ' ' . $anchor
                 . ' ORDER BY m.id ' . ($dir === 'up' ? 'DESC' : 'ASC')
                 . ' LIMIT ' . $min;
            $rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
            if ($dir === 'up') { $rows = array_reverse($rows); }   // 取最大的 min 条后按原序返回
            $slice = $rows;
        } elseif (isset($_GET['around'])) {
            // 跳转定位：返回以目标消息 id 为中心的片段（前后各约一半 limit），用于“跳到某条消息区域”
            $target = (int)$_GET['around'];
            $targetSeq = seqOf($target);
            if ($targetSeq < 0) {
                $slice = [];
            } else {
                $half = max(1, intdiv($limit, 2));
                $start = max(0, $targetSeq - $half);
                $end = min($count - 1, $targetSeq + $half);
                // 若因接近边界导致不足 limit，尽量向另一侧补足，让片段至少含 target
                while (($end - $start + 1) < $limit && $start > 0) { $start--; }
                while (($end - $start + 1) < $limit && $end < $count - 1) { $end++; }
                $take = $end - $start + 1;
                $rows = $pdo->query('SELECT id, author, content, time FROM messages ORDER BY id LIMIT ' . $take . ' OFFSET ' . $start)->fetchAll(PDO::FETCH_ASSOC);
                foreach ($rows as $i => &$m) { $m['seq'] = $start + $i; }
                $slice = $rows;
            }
        } elseif (isset($_GET['minId']) && isset($_GET['maxId'])) {
            $lo = (int)$_GET['minId'];
            $hi = (int)$_GET['maxId'];
            if ($lo > $hi) { $t = $lo; $lo = $hi; $hi = $t; }
            $st = $pdo->prepare('SELECT id, author, content, time,'
                . ' (SELECT COUNT(*) FROM messages m2 WHERE m2.id <= m.id) - 1 AS seq'
                . ' FROM messages m WHERE m.id >= ? AND m.id <= ? ORDER BY m.id');
            $st->execute([$lo, $hi]);
            $slice = $st->fetchAll(PDO::FETCH_ASSOC);
        } elseif (isset($_GET['upto'])) {
            $upto = (int)$_GET['upto'];
            $end = $upto;                      // 取 <= upto 的最新 limit 条
            if ($end >= $count) { $end = $count - 1; }
            $start = $end - $limit + 1;
            if ($start < 0) { $start = 0; }
            $take = $end - $start + 1;
            $rows = $pdo->query('SELECT id, author, content, time FROM messages ORDER BY id LIMIT ' . $take . ' OFFSET ' . $start)->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rows as $i => &$m) { $m['seq'] = $start + $i; }
            $slice = $rows;
        } else {
            $from = isset($_GET['from']) ? (int)$_GET['from'] : -1; // 默认 -1 => 空
            $start = $from + 1;
            $take = max(0, min($limit, $count - $start));
            if ($take > 0) {
                $rows = $pdo->query('SELECT id, author, content, time FROM messages ORDER BY id LIMIT ' . $take . ' OFFSET ' . $start)->fetchAll(PDO::FETCH_ASSOC);
                foreach ($rows as $i => &$m) { $m['seq'] = $start + $i; }
                $slice = $rows;
            }
        }
        foreach ($slice as &$m) { $m['time'] = (int)$m['time']; }

        jsonExit([
            'ok' => true,
            'count' => $count,
            'firstId' => $first,
            'slice' => $slice,
        ]);
        break;

    // GET /api/search?p=<密码>&q=<文本>  —— 全局搜索：内容子串匹配；id 仅精确匹配（纯数字，可带 # 前缀）
    case 'search':
        $acc = findByPassword($_GET['p'] ?? null);
        if ($acc === null) { deny('无访问权限'); }
        $q = trim((string)($_GET['q'] ?? ''));
        $results = [];
        if ($q !== '') {
            $pdo = db();
            $idQuery = preg_replace('/^#/', '', $q);        // 允许 #12 形式按 id 搜索
            // id 只做精确匹配：查询必须是纯数字才参与 id 匹配（"12" 不再命中 id 123）
            $idNum = preg_match('/^\d+$/', $idQuery) ? (int)$idQuery : null;
            $lower = mb_strtolower($q);
            $batch = 200;                                    // 每次只取 200 条，不整体加载
            $offset = 0;
            while (true) {
                $rows = $pdo->query('SELECT id, author, content, time FROM messages ORDER BY id LIMIT ' . $batch . ' OFFSET ' . $offset)->fetchAll(PDO::FETCH_ASSOC);
                if (!$rows) break;
                foreach ($rows as $i => $m) {
                    $id = (int)$m['id'];
                    // 内容匹配（子串）
                    $content = mb_strtolower((string)$m['content']);
                    $contentMatch = $content !== '' && mb_strpos($content, $lower) !== false;
                    // id 精确匹配
                    $idMatch = ($idNum !== null && $id === $idNum);
                    if ($contentMatch || $idMatch) {
                        // 纯文本摘要（去掉 markdown/HTML 记号）
                        $plain = preg_replace('/\s+/u', ' ', strip_tags(preg_replace('/[\[\]#*`>|~]/u', ' ', $m['content'])));
                        $plain = trim($plain);
                        // 摘要取“命中词附近的区域”，并在命中处用 \u0003…\u0004 包裹以便前端高亮
                        $contentLower = mb_strtolower($plain);
                        $pos = ($contentMatch && isset($lower) && $lower !== '') ? mb_strpos($contentLower, $lower) : false;
                        if ($pos !== false) {
                            $before = 15; $after = 45;                 // 命中词前/后各留多少字
                            $start = max(0, $pos - $before);
                            $wStart = $pos;                            // 命中片段起点
                            $hitLen = mb_strlen($lower);
                            $segLen = $after + ($pos - $start) + $hitLen;
                            $lead = $start > 0 ? '…' : '';
                            $trail = ($start + $segLen) < mb_strlen($plain) ? '…' : '';
                            $snippet = $lead
                                     . mb_substr($plain, $start, $pos - $start)
                                     . "\u{0003}" . mb_substr($plain, $pos, $hitLen) . "\u{0004}"
                                     . mb_substr($plain, $pos + $hitLen, max(0, $segLen - ($pos - $start) - $hitLen))
                                     . $trail;
                        } else {
                            $snippet = $plain === '' ? '(空内容)' : mb_substr($plain, 0, 60);
                        }
                        // 命中 id 时给摘要标注来源，便于识别
                        if ($idMatch) $snippet .= '  [id:' . $id . ']';
                        $results[] = [
                            'id' => $id,
                            'seq' => $offset + $i,
                            'author' => $m['author'],
                            'time' => (int)$m['time'],
                            'snippet' => $snippet,
                            'idMatch' => $idMatch,
                        ];
                    }
                }
                $offset += $batch;
                if (count($rows) < $batch) break;
            }
            // 排序：id 精确匹配的排在最前；其余（含 id 匹配组内部）按 id 倒序（新的在前）
            usort($results, function($a, $b){
                if ($a['idMatch'] !== $b['idMatch']) return $a['idMatch'] ? -1 : 1;
                return $b['id'] - $a['id'];
            });
        }
        jsonExit(['ok' => true, 'query' => $q, 'count' => count($results), 'results' => $results]);
        break;

    // GET /api/content?p=<密码>&id=<id>  —— 取单条消息完整内容(富文本)，供片内懒加载
    case 'content':
        $acc = findByPassword($_GET['p'] ?? null);
        if ($acc === null) { deny('无访问权限'); }
        $id = (int)($_GET['id'] ?? -1);
        $st = db()->prepare('SELECT content FROM messages WHERE id = ?');
        $st->execute([$id]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        jsonExit(['ok' => true, 'content' => $row ? (string)$row['content'] : '']);
        break;

    // POST /api/send?p=<密码>   请求体 JSON: {content}
    case 'send':
        $acc = findByPassword($_GET['p'] ?? null);
        if ($acc === null) { deny('无访问权限'); }
        if (!$acc[1]) {
            jsonExit(['ok' => false, 'error' => '访客账户无权发送消息']);
        }
        $in = json_decode(file_get_contents('php://input'), true);
        $content = trim((string)($in['content'] ?? ''));
        if ($content === '' || $content === '<p><br></p>') {
            jsonExit(['ok' => false, 'error' => '消息不能为空']);
        }
        // 只保留文本长度上限，避免滥用
        if (mb_strlen($content) > 50000) {
            jsonExit(['ok' => false, 'error' => '消息过长']);
        }

        $msg = insertMessage($acc[0], $content);
        $count = (int)db()->query('SELECT COUNT(*) FROM messages')->fetchColumn();
        jsonExit(['ok' => true, 'msg' => $msg, 'count' => $count]);
        break;

    // POST /api/delete?p=<密码>&id=<id>  —— 只能删除自己的消息
    case 'delete':
        $acc = findByPassword($_GET['p'] ?? null);
        if ($acc === null) { deny('无访问权限'); }
        if (!$acc[1]) {
            jsonExit(['ok' => false, 'error' => '访客账户无权删除消息']);
        }
        $id = (int)($_GET['id'] ?? -1);
        // 先确认消息存在，再校验作者，最后按“id + 作者”双条件删除
        $st = db()->prepare('SELECT author FROM messages WHERE id = ?');
        $st->execute([$id]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            jsonExit(['ok' => false, 'error' => '消息不存在']);
        }
        if ($row['author'] !== $acc[0]) {
            jsonExit(['ok' => false, 'error' => '只能删除自己的消息']);
        }
        if (deleteMessageById($id, $acc[0])) {
            jsonExit(['ok' => true]);
        }
        jsonExit(['ok' => false, 'error' => '消息不存在']);
        break;

    // POST /api/upload?p=<密码>    上传文件（≤10MB），存到 data/uploads，返回可访问 URL
    case 'upload':
        $acc = findByPassword($_GET['p'] ?? null);
        if ($acc === null) { deny('无访问权限'); }
        if (!$acc[1]) { jsonExit(['ok' => false, 'error' => '访客账户无权上传']); }
        $f = $_FILES['file'] ?? null;
        if (!$f || !isset($f['error']) || $f['error'] !== UPLOAD_ERR_OK) {
            jsonExit(['ok' => false, 'error' => '未收到文件']);
        }
        $maxBytes = 10 * 1024 * 1024;                 // 10MB
        if ($f['size'] > $maxBytes) {
            jsonExit(['ok' => false, 'error' => '文件超过 10MB 上限']);
        }
        $orig = basename($f['name'] ?? 'file');
        // 仅拒绝 PHP 家族等可在服务端执行的脚本；其余类型一律支持，保留原名
        $ext = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
        $phpLike = ['php','php3','php4','php5','php7','php8','phtml','pht','phar','cgi','pl','py','htaccess'];
        if (in_array($ext, $phpLike, true) || strtolower($orig) === '.htaccess') {
            jsonExit(['ok' => false, 'error' => '禁止上传脚本文件：.' . $ext]);
        }
        $dirRoot = __DIR__ . '/uploads';
        if (!is_dir($dirRoot) && !@mkdir($dirRoot, 0755, true)) {
            jsonExit(['ok' => false, 'error' => '无法创建上传目录']);
        }
        // 每个文件一个独立文件夹，保留原始文件名，避免重名冲突
        $folder = bin2hex(random_bytes(8));
        $dir = $dirRoot . '/' . $folder;
        if (!@mkdir($dir, 0755, true)) {
            jsonExit(['ok' => false, 'error' => '无法创建文件目录']);
        }
        // 清理文件名中的路径与危险字符，仅保留可读原名
        $safeName = preg_replace('/[^\w.\-\s\p{Han}]/u', '_', $orig);
        $safeName = trim($safeName, " \t\n\r\0\x0B.");
        if ($safeName === '') { $safeName = 'file'; }
        $dest = $dir . '/' . $safeName;
        $i = 1;
        while (file_exists($dest)) {
            $pi = pathinfo($safeName);
            $dest = $dir . '/' . $pi['filename'] . '_' . ($i++) . ($pi['extension'] ? '.' . $pi['extension'] : '');
        }
        if (!move_uploaded_file($f['tmp_name'], $dest)) {
            jsonExit(['ok' => false, 'error' => '保存失败']);
        }
        // 返回的 URL 对文件名做 URL 编码，避免空格、中文等字符在消息/链接中产生歧义或被截断
        jsonExit(['ok' => true, 'url' => 'uploads/' . $folder . '/' . rawurlencode(basename($dest))]);
        break;

    // GET /api/fonts?p=<密码>  —— 列出 fonts/ 目录下可选的字体文件（供字体面板下载到本地）
    case 'fonts':
        $acc = findByPassword($_GET['p'] ?? null);
        if ($acc === null) { deny('无访问权限'); }
        $dir = __DIR__ . '/fonts';
        $list = [];
        if (is_dir($dir)) {
            $it = scandir($dir);
            foreach ($it ?: [] as $f) {
                if ($f === '.' || $f === '..') continue;
                $p = $dir . '/' . $f;
                if (!is_file($p)) continue;
                $ext = strtolower(pathinfo($f, PATHINFO_EXTENSION));
                if (!in_array($ext, ['ttf', 'otf', 'woff', 'woff2', 'eot'], true)) continue;
                $mime = $ext === 'woff2' ? 'font/woff2'
                      : ($ext === 'woff' ? 'font/woff'
                      : ($ext === 'ttf' ? 'font/ttf'
                      : ($ext === 'otf' ? 'font/otf' : 'application/octet-stream')));
                $list[] = [
                    'file' => $f,
                    'name' => pathinfo($f, PATHINFO_FILENAME),
                    'size' => filesize($p),
                    'mime' => $mime,
                ];
            }
            usort($list, function($a, $b){ return strcmp($a['file'], $b['file']); });
        }
        jsonExit(['ok' => true, 'fonts' => $list]);
        break;

    default:
        jsonExit(['ok' => false, 'error' => '未知接口']);
}
} catch (\Throwable $e) {
    // 兜底：任何路由异常都返回 JSON，绝不返回 500 页（避免污染前端 fetch）
    http_response_code(500);
    jsonExit(['ok' => false, 'error' => '服务端异常']);
}

/*
 * =============================================================
 *  前端  ——  单页应用（HTML / CSS / JS 全部内嵌，单文件）
 *  黑白极简、方角边框；猎户座编辑器(Orion)富文本格式渲染。
 * =============================================================
 */


