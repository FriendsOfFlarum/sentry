module.exports=function(e){var t={};function r(n){if(t[n])return t[n].exports;var a=t[n]={i:n,l:!1,exports:{}};return e[n].call(a.exports,a,a.exports,r),a.l=!0,a.exports}return r.m=e,r.c=t,r.d=function(e,t,n){r.o(e,t)||Object.defineProperty(e,t,{enumerable:!0,get:n})},r.r=function(e){"undefined"!=typeof Symbol&&Symbol.toStringTag&&Object.defineProperty(e,Symbol.toStringTag,{value:"Module"}),Object.defineProperty(e,"__esModule",{value:!0})},r.t=function(e,t){if(1&t&&(e=r(e)),8&t)return e;if(4&t&&"object"==typeof e&&e&&e.__esModule)return e;var n=Object.create(null);if(r.r(n),Object.defineProperty(n,"default",{enumerable:!0,value:e}),2&t&&"string"!=typeof e)for(var a in e)r.d(n,a,function(t){return e[t]}.bind(null,a));return n},r.n=function(e){var t=e&&e.__esModule?function(){return e.default}:function(){return e};return r.d(t,"a",t),t},r.o=function(e,t){return Object.prototype.hasOwnProperty.call(e,t)},r.p="",r(r.s=33)}({10:function(e,t){e.exports=flarum.core.compat["admin/app"]},33:function(e,t,r){"use strict";r.r(t);var n=r(10),a=r.n(n);a.a.initializers.add("fof/sentry",(function(){a.a.extensionData.for("fof-sentry").registerSetting({label:a.a.translator.trans("fof-sentry.admin.settings.dsn_label"),setting:"fof-sentry.dsn",type:"url"}).registerSetting({label:a.a.translator.trans("fof-sentry.admin.settings.user_feedback_label"),setting:"fof-sentry.user_feedback",type:"boolean"}).registerSetting({label:a.a.translator.trans("fof-sentry.admin.settings.monitor_performance_label"),setting:"fof-sentry.monitor_performance",type:"number",min:0,max:100}).registerSetting({label:a.a.translator.trans("fof-sentry.admin.settings.javascript_label"),setting:"fof-sentry.javascript",type:"boolean"}).registerSetting({label:a.a.translator.trans("fof-sentry.admin.settings.javascript_console_label"),setting:"fof-sentry.javascript.console",type:"boolean"}).registerSetting({label:a.a.translator.trans("fof-sentry.admin.settings.javascript_trace_sample_rate"),setting:"fof-sentry.javascript.trace_sample_rate",type:"number",min:0,max:100}).registerSetting({label:a.a.translator.trans("fof-sentry.admin.settings.send_user_emails_label"),setting:"fof-sentry.send_emails_with_sentry_reports",type:"boolean"})}))}});
//# sourceMappingURL=admin.js.map