!function(e){var n={};function t(r){if(n[r])return n[r].exports;var o=n[r]={i:r,l:!1,exports:{}};return e[r].call(o.exports,o,o.exports,t),o.l=!0,o.exports}t.m=e,t.c=n,t.d=function(e,n,r){t.o(e,n)||Object.defineProperty(e,n,{enumerable:!0,get:r})},t.r=function(e){"undefined"!=typeof Symbol&&Symbol.toStringTag&&Object.defineProperty(e,Symbol.toStringTag,{value:"Module"}),Object.defineProperty(e,"__esModule",{value:!0})},t.t=function(e,n){if(1&n&&(e=t(e)),8&n)return e;if(4&n&&"object"==typeof e&&e&&e.__esModule)return e;var r=Object.create(null);if(t.r(r),Object.defineProperty(r,"default",{enumerable:!0,value:e}),2&n&&"string"!=typeof e)for(var o in e)t.d(r,o,function(n){return e[n]}.bind(null,o));return r},t.n=function(e){var n=e&&e.__esModule?function(){return e.default}:function(){return e};return t.d(n,"a",n),n},t.o=function(e,n){return Object.prototype.hasOwnProperty.call(e,n)},t.p="/",t(t.s=8)}({8:function(e,n,t){e.exports=t(9)},9:function(e,n){Garnish.$doc.ready((function(){new QarrLineChart(".chart-explorer-container.chart-reviews","owldesign\\qarr\\elements\\Review"),new QarrLineChart(".chart-explorer-container.chart-questions","owldesign\\qarr\\elements\\Question");var e=new QarrDonutChart("#reviews-donut","owldesign\\qarr\\elements\\Review"),n=new QarrDonutChart("#questions-donut","owldesign\\qarr\\elements\\Question");e.on("pieIn",(function(e){$("#widget-reviews").find(".stat-"+e.data.handle).addClass("has-hover")})),e.on("pieOut",(function(e){$(".stat-item").removeClass("has-hover")})),e.on("response",(function(e){var n=$("#widget-reviews");$.each(e.data,(function(e,t){n.find(".stat-"+t.handle).find(".stat-value").html(Math.round(100*t.percent)+"%")}))})),n.on("pieIn",(function(e){$("#widget-questions").find(".stat-"+e.data.handle).addClass("has-hover")})),n.on("pieOut",(function(e){$(".stat-item").removeClass("has-hover")})),n.on("response",(function(e){var n=$("#widget-questions");$.each(e.data,(function(e,t){n.find(".stat-"+t.handle).find(".stat-value").html(Math.round(100*t.percent)+"%")}))}))}))}});