/*
 Copyright (c) 2003-2017, CKSource - Frederico Knabben. All rights reserved.

 For licensing, see LICENSE.md or http://ckeditor.com/license

*/
CKEDITOR.plugins.add("uicolor",{requires:"dialog",lang:"af,ar,bg,ca,cs,da,de,el,en,es,et,fi,fr,gl,hr,hu,it,ja,nl,no,pl,pt,pt-br,ru,sk,sl,sv,tr,uk,zh",icons:"uicolor",hidpi:!0,init:function(a){CKEDITOR.env.ie6Compat||(a.addCommand("uicolor",new CKEDITOR.dialogCommand("uicolor")),a.ui.addButton&&a.ui.addButton("UIColor",{label:a.lang.uicolor.title,command:"uicolor",toolbar:"tools,1"}),CKEDITOR.dialog.add("uicolor",this.path+"dialogs/uicolor.js"),CKEDITOR.scriptLoader.load(CKEDITOR.getUrl("plugins/uicolor/yui/yui.js")),
CKEDITOR.document.appendStyleSheet(CKEDITOR.getUrl("plugins/uicolor/yui/assets/yui.css")))}});