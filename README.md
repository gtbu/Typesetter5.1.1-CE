This Typesetter 5.1.1ce - version is an improved fork of Typesetter 5.1.1b1 of summer 2019.
  
* The momentary 5.2RC (https://github.com/Typesetter/Typesetter) has nearly all of these features except a blue dashboard and blue login and mod deflate 
(for slow providers and smartphones essential) - and some improvements over this version like Bootstrap4-Templategenerator. 

* It contains the newest security issues and some small modifications and updates and runs with php 5.6+ up to php 7.3.  (5.4+ , if less and scss-compilers are not used, or with activated old compilers by renaming  css-old.php and renaming 5.6 to 5.4 in the common.php) 

* It is as well under GPL 2.1 license(https://www.gnu.org/licenses/old-licenses/lgpl-2.1.de.html). 

* This version contains ckeditor 4.14 (WL-Version). At http://typesetter5.bplaced.net/Ckeditor You can download ALL  ckeditor versions ! Further plugins like codemirror(https://ckeditor.com/cke4/addon/codemirror) are avaliable from ckeditor.com  and are installable at settings -> ckeditor. (Others like smethods are available at https://github.com/Sestolab/smethods and in this gtbu-git )
* It contains the new sass-compiler for bootstrap 4.x  (php 5.6+ )
* It contains a  faster less2.53-compiler  (php 5.6+ )
* PHPmailer 5.2.7 - version without known security issues (https://github.com/tpbu/PHPMailer-5.27)


Contained Plugins with small bugs :
* The rather old highlighter-plugin has no highlighting, though there are other possibilities of  ckeditor plugins with simliar function 

* For problems with PHP Mailer please study  https://github.com/PHPMailer/PHPMailer/wiki/Troubleshooting  and the forum

* QUESTIONS for installations and development ? Please look before in <a href="https://github.com/gtbu/Typesetter5.2/wiki" target=_blank> this  Wiki</a> 
or <a href="https://www.typesettercms.com/Docs" target=_blank>in the Forum</a>.
------------------------------------------------------------------------------------------------------

# Typesetter CMS #

Open source CMS written in PHP focused on ease of use with true WYSIWYG editing and flat-file storage.
* [Typesetter Home](http://www.typesettercms.com)
* [Typesetter Download](http://www.typesettercms.com/Download)
* [Typesetter Demo](http://www.typesettercms.com/Demo)
* [Typesetter Forum](http://www.typesettercms.com/Special_Forum)

## Requirements ##
* PHP 5.6+
* PHP Safe Mode Off

## Installation ##
1. Download the latest stable release of Typesetter from TypesetterCMS.com

2. Upload the extracted contents to your server

3. Using your web browser, navigate to the folder you just uploaded the unzipped contents to

4. Complete the installation form and submit

You can find more detailed installation information on [TypesetterCMS.com](http://www.typesettercms.com/Docs/Installation)


## Contribute ##
Submitting bug fixes and enhancements is easy:

1. Log in to GitHub

2. Fork the Typesetter Repository
  * https://github.com/Typesetter/Typesetter
  * Click "Fork" and you'll have your very own copy of the Typesetter source code at http://github.com/{your-username}/Typesetter

3. Edit files within your fork.
  This can be done directly on GitHub.com at http://github.com/{your-username}/Typesetter

4. Submit a Pull Request (tell Typesetter about your changes)
  * Click "Pull Request"
  * Enter a Message that will go with your commit to be reviewed by core committers
  * Click “Send Pull Request”

### Multiple Pull Requests and Edits ###
When submitting pull requests, it is extremely helpful to isolate the changes you want included from other unrelated changes you may have made to your fork of Typesetter. The easiest way to accomplish this is to use a different branch for each pull request. There are a number of ways to create branches within your fork, but GitHub makes the process very easy:

1. Start by finding the file you want to edit in Typesetter's code repository at https://github.com/Typesetter/Typesetter.
2. Once you have located the file, navigate to the code view and click "Edit". For example, if you want to change the /include/common.php file, the "Edit" button would appear on this page: https://github.com/Typesetter/Typesetter/blob/master/include/common.php
3. Now, edit the file as you like then click "Propose File Change"
