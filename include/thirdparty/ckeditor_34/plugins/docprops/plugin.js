/*
 Copyright (c) 2003-2017, CKSource - Frederico Knabben. All rights reserved.

 For licensing, see LICENSE.md or http://ckeditor.com/license

*/
CKEDITOR.plugins.add("docprops",{requires:"wysiwygarea,dialog,colordialog",lang:"af,ar,bg,ca,cs,da,de,el,en,es,et,fi,fo,fr,gl,hr,hu,it,ja,lt,nl,no,pl,pt,pt-br,ru,sk,sl,sv,tr,uk,zh",icons:"docprops,docprops-rtl",hidpi:!0,init:function(a){var b=new CKEDITOR.dialogCommand("docProps");b.modes={wysiwyg:a.config.fullPage};b.allowedContent={body:{styles:"*",attributes:"dir"},html:{attributes:"lang,xml:lang"}};b.requiredContent="body";a.addCommand("docProps",b);CKEDITOR.dialog.add("docProps",this.path+"dialogs/docprops.js");
a.ui.addButton&&a.ui.addButton("DocProps",{label:a.lang.docprops.label,command:"docProps",toolbar:"document,30"})}});