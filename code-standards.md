# Archaeopterix Code Standards

This document defines formatting and style rules for HTML, CSS, SASS, JS and PHP in order to improve collaboration, code quality, and maintainability. It is important that the standards set forth in this document are closely followed and strictly enforced so that code quality is maintained. The ultimate goal is to have the code look like a single person wrote it, no matter how many contributors.

__Please [read](https://chrome.google.com/webstore/detail/markdown-preview/jmchmkecamhbiokiopfpnfgbidieafmd), [sign](#signature), & [commit](#or-else).__

*Denotes standards that are less common.

## Table of Contents

1. [Cross Language Rules](#cross-language-rules)
2. [Readability](#readability)
3. [HTML](#html)
	1. [Naming Conventions & File Structure](#html-naming-conventions-file-structure)
	2. [Indentation\*](#html-indentation)
	3. [Line Length\*](#html-line-length)
	4. [Capitalization](#html-capitalization)
	5. [Tags](#html-tags)
	6. [Inline Styles](#html-inline-styles)
	7. [Semantics](#html-semantics)
	8. [Validity](#html-validity)
	9. [Comments\*](#html-comments)
	10. [Text](#html-text)
	11. [Headings](#html-headings)
	12. [Forms](#html-forms)
4. [CSS & SASS](#css-sass)
	1. [Naming Conventions\*](#css-sass-naming-conventions)
	2. [Indentation\*](#css-sass-indentation)
	3. [White Space](#css-sass-white-space)
	4. [Capitalization](#css-sass-capitalization)
	5. [Property Order\*](#css-sass-property-order)
	6. [Units](#css-sass-units)
	7. [Specificity](#css-sass-specificity)
	8. [Legacy Browser Support\*](#css-sass-legacy-browser-support)
	9. [Comments\*](#css-sass-comments)
	10. [File Structure](#css-sass-file-structure)
5. [JS](#js)
	1. [Naming Conventions](#js-naming-conventions)
	2. [Indentation\*](#js-indentation)
	3. [White Space](#js-white-space)
	4. [Line Length\*](#js-line-length)
	5. [Line Spacing\*](#js-line-spacing)
	6. [Function & Method Length](#js-function-method-length)
	7. [Ternary Operators](#js-ternary-operators)
	8. [DOM Manipulation](#js-dom-manipulation)
	9. [Quality](#js-quality)
	10. [JSDoc\*](#js-jsdoc)
	11. [Comments\*](#js-comments)
	12. [Animations\*](#js-animations)
	13. [File Structure](#js-file-structure)
6. [PHP](#php)
	1. [Naming Conventions\*](#php-naming-conventions)
	2. [Indentation\*](#php-indentation)
	3. [Brace Usage](#php-brace-usage)
	4. [Code Commenting](#php-code-commenting)
	5. [White Space](#php-white-space)
	6. [Line Length\*](#php-line-length)
	7. [Line Spacing\*](#php-line-spacing)
	8. [Function & Method Length](#php-function-method-length)
	9. [Ternary Operators\*](#php-ternary-operators)
	10. [Models\*](#php-mvc-models)
	11. [Views\*](#php-mvc-views)
	12. [Controllers\*](#php-mvc-controllers)
	13. [Helpers\*](#php-mvc-helpers)
	14. [File Structure](#php-mvc-file-structure)
7. [GIT](#git)
	1. [Commits](#git-commits)
8. [Code Completeness\*](#code-completeness)

* * *

<a name="cross-language-rules"></a>
## 1. Cross Language Rules

All files must use Unix style line-endings `\n` must be commented while you are writing it. Do not go back after the fact to add explanatory comments because chances are you not only will neglect to do this, you will likely have to spend time figuring out your own code.

<a name="readability"></a>
## 2. Readability

Write code for humans, not machines.

Other developers will eventually need to maintain and extend your code, so write your code with them in mind. Use whitespace, tabbing, and naming to be as expressive as possible, and suptliment comments only when necessary. Each line of whitespace or tab should communicate.

Avoid optimizing code for compression rather than readability. Since there are server-side processes that handle concatenation and compression, we should not concern ourselves too much with saving a few bytes here and there.

<a name="html"></a>
## 3. HTML

<a name="html-naming-conventions-file-structure"></a>
### 3.1 Naming Conventions & File Structure

All template file names should be `hyphen-cased`. Use descriptive file names that read from general to specific. __i.e.__

		// Incorrect. Css and js are more specific than header.
			css-header.phtml
			html-footer.phtml
			html-header.phtml
			js-header.phtml
		// Correct!
			footer-html.phtml
			header-css.phtml
			header-html.phtml
			header-js.phtml

		// Incorrect.
			content-detail-product.phtml
			detail-product-sidebar.phtml
			list-product.phtml
			list-services.phtml
		// Correct!
			product-detail-content.phtml
			product-detail-sidebar.phtml
			product-list.phtml
			services-list.phtml

		// Incorrect.
			main-nav.phtml
			secondary-nav.phtml
			sidebar-nav.phtml
		// Correct!
			nav-main.phtml
			nav-secondary.phtml
			nav-sidebar.phtml

This not only clarifies file contents, but also helps organize your files by grouping similar files together. Your end file structure should follow the same logic and order as your sitemap.

		|-- -js 					// JS source files
		|-- .ant 					// Files for jenkins tasks
		|-- app						// MVC - App logic
		|	|-- controllers			// MVC - Controllers
		|	|-- helpers				// MVC - Helpers functions for controllers
		|	|-- models				// MVC - Models
		|	|-- views				// MVC - Views
		|	|-- webroot				// Exposed content to everyone (Assets)
		|		|-- css 			// CSS compiled files
		|		|-- fonts 			// Fonts used in the proyect
		|		|-- images			// Proyect images
		|		|-- js 				// JS compiled files
		|		|-- plugins 		// Third-party JS script compound by many kind of files
		|-- config 					// Config proyect files (db_settings, host)
		|-- migrations 				// MVC - Migrations files (dumps, seeds, creations)
		|-- sass 					// Compass files in scss format
		|-- vendors 				// PHP clases, snippets, etc. not owned by us

An `id` should be used for all buttons and wrappers whose content is appended through Javacsript.

<a name="html-indentation"></a>
### 3.2 Indentation\*

Tabs are preferred over spaces for indentation. Tab size should be controlled by the preferences in the text editor of whoever is writing/editing the code.

Tab each child element one more indent than its parent. Use an empty line to indicate logical separations between sibling elements. Use a new line for every block, list, or table element, and indent every such child element.

<a name="html-line-length"></a>
### 3.3 Line Length\*

Lines should ideally be less than 80 characters. In rare cases, longer lines are acceptable but should never exceed 120 characters.

<a name="html-capitalization"></a>
### 3.4 Capitalization

All markup should be lowercase. When quoting attributes values, use double quotation marks. It is preferred that attributes be alphabetized for consistency, with CSS classes organized from from most specific to least specific, __i.e.__ page specific classes before image replacement helper classes.

<a name="html-tags"></a>
### 3.5 Tags\*

For HTML elements that have more than two attributes or opening tags that exceeds the ideal line length, each attribute should be broken out on a new line and indented a further level. It is preferred, that the closing `>` should be on the same line as the last attribute. If an element has attributes on individual lines, all children elements should be indented a tab further while the closing tag should share the same indentation level as the opening tag. __i.e.__

		<a
			class="add-member-button"
			data-member_id="43"
			href="/add/member"
			id="add-member-button">
				Create Account
		</a>

All the `<a>` tags shlould have a href to accomodate accessibility and web crawlers. Also, `<img>` tags should have a src url. Changing image urls via Javascript for responsive design should be a progressive enhancement.


<a name="html-inline-styles"></a>
### 3.6 Inline Styles

Use inline styles only where necessitated by integration. Place all `<script>` elements directly above the closing `<body>` tag with the exception of Modernizr, which must be placed directly above the closing `<head>` tag.

<a name="html-semantics"></a>
### 3.7 Semantics

All markup should be as semantically valid and accurate as possible. Elements should be expressive and chosen based on content and purpose. Avoid div's unless the element is simply used for stylistic purposes. When in doubt, consult [HTML5 Doctor](http://html5doctor.com/) specifically this fantastic [flow chart](http://html5doctor.com/downloads/h5d-sectioning-flowchart.pdf).

It is preferred that a `<ul>` element be used inside a `<nav>` element.

Avoid empty tags where ever possible.

<a name="html-validity"></a>
### 3.8 Validity

Valid, W3C Compliant markup must be used at all times, this includes using `label`, `fieldset`, and `legend` elements properly. If necessary, use a [validator](http://validator.w3.org/).

HTML5 document type and UTF-8 encoding should be used and type attributes for style sheets and scripts should be omitted.

For [void elements](http://dev.w3.org/html5/markup/syntax.html#void-element), include a `/` character before the closing `>`.

<a name="html-comments"></a>
### 3.9 Comments\*

Comments should be used to explain any atypical or complex markup.

Comment the closing tag of main elements that contain more than one child or more than one level of children. __i.e.__ `<!-- .class -->` or `<!-- #id -->`

<a name="html-text"></a>
### 3.10 Text

Any purely stylistic variations such as all-caps or all-lowercase should be handled by CSS properties like `text-transform` unless such a presentation is not possible in CSS like textInCamelCase. Note that this does not apply to elements like acronym and abbr, both of which can appropriately contain all-caps text.

<a name="html-headings"></a>
### 3.11 Headings

Headings `h1 - h6` are used to label sections of content or modules.

The h1 element is reserved for the main heading of the page such as an article title. Only include a single h1 per page. This is good for SEO and semantically appropriate as well.

<a name="html-forms"></a>
### 3.12 Forms

Forms must be marked up in as accessible/platform-independent a manner as possible. Form elements must be contained inside a block-level element, preferably a list. All form controls must have an ID attribute that matches its name and a descriptive label, even if the label ends up hidden from sighted users.

When using radio buttons or checkboxes, the preferred pattern is to not use the for attribute and simply wrap the input inside the label. In this case, you can leave an ID off the input.

		<label><input type="radio" name="x" value="y" /> Label Text</label>

If your form contains a file input, set the `enctype="multipart/form-data"` attribute on the form itself.

Every form must have an actual submit button.

<a name="css-sass"></a>
## 4. CSS & SASS

<a name="css-sass-naming-conventions"></a>
### 4.1 Naming Conventions\*

It's important to use clear, thoughtful, and appropriate names for classes and id's. Both should read from general to specific. __i.e.__ `button-submit` Use verbose, descriptive names.

Separate words in id's and class names by a hyphen; no underscores or camel case. This improves readability and scannability.

Styles should not be based on a specific hosting template or page id. This allows them to be re-usable within the site.

<a name="css-sass-indentation"></a>
### 4.2 Indentation\*

Tabs are preferred over spaces for indentation. Tab size should be controlled by the preferences in the text editor of whoever is writing/editing the code. Use one level of indentation for each declaration.

By default, always start with one level of indentation. Use indentation to indicate HTML hierarchy. __i.e.__ If a `h2` is contained inside of `div.class`, the referenced `h2` should be indented one tab further than `div.class` in the stylesheet. This mirrors the formatting of nested elements in SASS.

<a name="css-sass-white-space"></a>
### 4.3 White Space

Consistency in use of whitespace is important; whitespace improves readability.

Remove trailing whitespace at the end of lines.

Always put each declaration and each selector on a separate line, __i.e.__

		h1,
		h2,
		h3 {
			border: 1px solid #333;
			color: #333;
		}

Include a single space before the opening brace of a ruleset.

Include a single space after the colon of a declaration. No space before.

Include a semi-colon at the end of the last declaration in a declaration block.

Place the closing brace of a ruleset in the same column as the first character of the ruleset.

Separate each ruleset by a blank line.

<a name="css-sass-capitalization"></a>
### 4.4 Capitalization

All code with the exception of comments must be lowercase.

Use lowercase in Table of Contents.

Capitalize font names `"Arial", "Georgia"`. Don't capitalize font families `serif, sans-serif, monospace`.

<a name="css-sass-property-order"></a>
### 4.5 Property Order\*

All properties should be placed in alphanumeric order (0-9 A-Z) ignoring browser-specific prefixes. If browser-specific prefixes are needed, the should be grouped in alphanumeric order but placed in overall order based on the CSS3 property name. __i.e.__

		background: #fff;
		border-radius: 5px;
		-moz-border-radius: 5px;
		-webkit-border-radius: 5px;
		color: #333;
		display: inline-block;
		text-align: center;
		width: 100%;

<a name="css-sass-units"></a>
### 4.6 Units

Use `px` for `font-size` and `line-height`. Where allowed, don't specify units for zero-values. __i.e.__ `margin: 0;` or `top: 0;`

Pad all decimals. __i.e.__ `rgba(0, 0, 0, 0.5)`

Use lowercase hex color codes and 3 character shorthand where applicable.

When using percentages based on complex calculations dictated by design, comment to clarify. __i.e.__

		.individuals-families {
			width: 32.377740304%; // 192px/593px
		}

<a name="css-sass-specificity"></a>
### 4.7 Specificity

With SASS, avoid using overly specific selectors. Only nest selectors when necessary as to not produce inefficient CSS that is hard to overwrite.

<a name="css-sass-legacy-browser-support"></a>
### 4.8 Legacy Browser Support\*

Do not use CSS hacks for targeting legacy browsers. Instead use the class on the HTML tag to override default styles.

<a name="css-sass-comments"></a>
### 4.9 Comments\*

Each SASS file should include the file name on the first line. Denote major elements or sections with main level flags. The flag should be the selector prefixed by an underscore. Flags should be indented one less tab than the selector they reference.

		//	-----------------------------------------------------------------------------
		//	_#main-level
		//	-----------------------------------------------------------------------------
			#main-level {}

Include as many sub level flags as necessary to break the file into logical sections.

		//	_.sub-level
		//	-----------------------------------------------------------------------------
			.sub-level {}

All flags should be included in a table of contents at the top of the file. Use tabs to clarify hierarchy

		//	-----------------------------------------------------------------------------
		//	Table of Contents (keep up-to-date)
		//	-----------------------------------------------------------------------------
		//
		//	_#main-level
		//		_.sub-level
		//
		//	-----------------------------------------------------------------------------

<a name="css-sass-file-structure"></a>
### 4.10 File Structure

All SASS files should be prefixed with an underscore and included in a single file so only one SASS file is compiled. Each file should be name `_module-name.breakpoint.scss` or `_page-name.breakpoint.scss`. Each module, page, or global should have a file for each breakpoint even if no styles are needed. The file structure should follow:

		|-- -sass			// Global files. (fonts, mixins, etc.)
		|	|-- modules		// Global modules.
		|	'-- pages		// Global pages.

<a name="js"></a>
## 5. JS

<a name="js-naming-conventions"></a>
### 5.1 Naming Conventions

File names should be lowercase with words separated by hyphens and should describe the file contents.

Functions and methods should be `camelCased` and read like well written prose. Functions that return a boolean should be interrogative. Functions that do something should begin with a verb. For example, a function that logs a user in could be named `loginUser()`, where as a function that checks if a user is logged in could be named `isLoggedIn()`.

Constants should be all uppercase with an underscore separating words.

Variables should also be `camelCased`. Variables that store jQuery objects should be prefixed with a `$`. Loops are the only occasion where a one letter variable is acceptable. Unless you already have a specific counting variable, use `i` as the variable for the outermost loop, then increment alphabetically. Do not use `l` (lowercase 'L') as it looks too similar to the number 'one'. __i.e.__

		for (var i = 0; i < 5; i++) {
			for (var j = 0; j < 4; j++) {
				for (var k = 0; k < 3; k++) {
					for (var m = 0; m < 3; m++) {
						doSomething(i, j, k, m);
					}
				}
			}
		}

Declare all variables using `var` before use to avoid [implied globals](http://www.webmechs.com/javascript-tutorials/nasty-javascript.php). All assignments must [end in a semicolon](http://google-styleguide.googlecode.com/svn/trunk/javascriptguide.xml?showone=Semicolons#Semicolons).

<a name="js-indentation"></a>
### 5.2 Indentation\*

For indentation, all code should follow strict [1TBS](http://en.wikipedia.org/wiki/Indent_style#Variant:_1TBS).

Tabs are preferred over spaces for indentation. Tab size should be controlled by the preferences in the text editor of whoever is writing/editing the code.

It is preferred that the values of object properties not be tabbed to align. __i.e.__

		// Incorrect.
			badObject = {
				oneProperty: 		'value',
				anotherProperty:	'value'
			}

		// Correct.
			goodObject = {
				oneProperty: 'value',
				anotherProperty: 'value'
			}

<a name="js-white-space"></a>
### 5.3 White Space

Control statements include `if`, `switch`, `while`, `for`, etc. These statements should have a single space before the opening parenthesis of the expression and a single space after the closing parenthesis. Operators within the expression should always be padded with a space before and after the operator, except for commas and semicolons, which should just have a single space after the operator. __i.e.__

		if (foo == bar) {
			foo++;
		}

		if (! foo) {
			foo = bar;
		}

		for (var i = 0; i < 10; i++) {
			foo[i] = bar;
		}

<a name="js-line-length"></a>
### 5.4 Line Length\*

Lines should ideally be less than 80 characters. In rare cases, longer lines are acceptable but should never exceed 120 characters. Following the line length standard not only improves the code's readability, but also helps enforce good, clean code.

When concatenating strings or dealing with chained methods, break them into multiple lines for improved readability. Use indentation to convey hierarchy. __i.e.__

		$thisSlide
			.find(HEADER_NAV_SUB_PANE_CLASS)
			.not('.' + HEADER_NAV_ACTIVE_CLASS)
				.addClass(HEADER_NAV_ACTIVE_CLASS);

<a name="js-line-spacing"></a>
### 5.5 Line Spacing\*

Use a blank line between logical breaks in your code. Multiple variable declarations should not have a blank line between them. Use a blank line before and after a control statement. Constants, variables, and cached jQuery objects should be grouped and separated by a blank line.

		var CONSTANT_NAME = 'selector';

		var variableName = 0,
			objectName = {};

		var $jqueryObject = $(CONSTANT_NAME),
			$jqueryObjectChild = $jqueryObject.find('selector');

		doSomething();

		if (variableName < 10) {
			doSomethingElse();
		} else {
			doSomethingNothing();
		}

		return false;

<a name="js-function-method-length"></a>
### 5.6 Function & Method Length

Functions and methods should do one thing and one thing only. It's been suggested that functions and methods be no longer than four lines of code. That is ideal, but not always possible. Regardless, the number of lines in your methods and functions will be short if they only do one thing. The best way to tell if your function only does one thing is to abstract until you can not abstract any more.

<a name="js-ternary-operators"></a>
### 5.7 Ternary Operators\*

Ternary operators are allowed only where logical and efficient. However, you should __never__ have nested ternary operators. Ternary conditions should be wrapped in parenthesis for readability, even if it's just a single conditional. Ternary operators may also be broken up into multiple lines if it makes sense to do so. When broken into multiple lines, the "?" and ":" operators should line up and be indented one tab further. __i.e.__

		a = (b == c && b == d)
			? $thisNameIsReallyReallyRidiculouslyLong
			: $notQuiteAsLong;

<a name="js-dom-manipulation"></a>
### 5.8 DOM Manipulation

Accessing and altering the DOM is slow. Minimize DOM manipulation.

Cache objects in variables if they are used more than once. Creating a jQuery object from an element or selector is a fairly expensive process.

		// Incorrect.
			$(this).addClass('something')
			$(this).doSomethingElse();
			callSomeFunction($(this));

		// Correct.
			var $this = $(this);

			$this.addClass('something');
			$this.doSomethingElse();
			callSomeFunction($this);

Rather than calling DOM manipulation functions within loops or in steps, building a string to inject into the document may be a faster method.

		// Incorrect.
			var $list = $('ul');

			for (var i = 0; i < 1000; i++) {
				$list.append('<li>Hello world\!</li>');
			}

		// Correct.
			var $list = $('ul'),
				items = '';

			for (i = 0; i < 1000; i++) {
				items += '<li>Hello world\!</li>';
			}

			$list.append(items);

To get all children of an element, use `$.children();`.

Any AJAX call should get the target url from an href attribute, form action, or appropriate data attribute.

<a name="js-quality"></a>
### 5.9 Quality

All JS written should be clean and efficient and must pass [JSHint](http://www.jshint.com/).

Never use `document.write()`, [`eval`](http://blogs.msdn.com/b/ericlippert/archive/2003/11/01/53329.aspx), [`continue`](http://javascript.crockford.com/code.html#continue%20statement), or [`with`](http://yuiblog.com/blog/2006/04/11/with-statement-considered-harmful). Also avoid passing strings to `setTimeout` and `setInterval`, which will causes them to use `eval`.

Use native event methods like `event.preventDefault();` and `event.stopPropagation();` to prevent default actions instead of `return false;`.

<a name="js-jsdoc"></a>
### 5.10 JSDoc\*

All methods and functions should be commented with a [JSDoc](http://usejsdoc.org/) comment. The block should at minimum contain the `@return` variable and a description, but may also contain one or multiple `@param` variables. Functions that do not return anything should be marked as `@return {void}`.

<a name="js-comments"></a>
### 5.11 Comments\*

Comments should be used infrequently. Your code should be written in a way that is easy to understand, making comments unnecessary. Before adding a comment, make sure that expressive function names, variable substitution, or refactoring are not the proper approach. If you do have to leave a comment, the comment should be directly above the line of code that it addresses.

<a name="js-animations"></a>
### 5.12 Animations\*

All animations, including hide and show, should be handled with classes and CSS3 transitions. Only in rare cases like parallax and one-to-one mouse or touch animaitons, is jQuery acceptable.

<a name="js-file-structure"></a>
### 5.13 File Structure

The `-js` should follow this structure.

		|-- -js				// Only main.js should be in this folder
		|	|-- libs		// Any included library such as jQuery in it's old folder with proper version
		|	|-- modules		// Modules are loaded by pages
		|	'-- utils		// Utilities are used by modules and are never loaded on pages

<a name="php"></a>
## 6. PHP

<a name="php-naming-conventions"></a>
### 6.1 Naming Conventions\*

For the file names see [Models](#php-models), [Views](#php-views), [Controllers](#php-controllers) and [Helpers](#php-helpers).

Functions and methods should be `camelCased` and read like well written prose. Functions that return a boolean should be interrogative. Functions that do something should begin with a verb. For example, a function that logs a user in could be named `loginUser()`, where as a function that checks if a user is logged in could be named `isLoggedIn()`.

Constants should be all uppercase with an underscore separating words.

Variables should also be `camelCased`. Loops are the only occasion where a one letter variable is acceptable. Unless you already have a specific counting variable, use `$i` as the variable for the outermost loop, then increment alphabetically. Do not use `l` (lowercase 'L') as it looks too similar to the number 'one'. __i.e.__

		for($i = 0; $i < 5; $i++):
			for($j = 0; $j < 4; $j++):
				for($k = 0; $k < 3; $k++):
					for($m = 0; $m < 3; $m++):
						doSomething($i, $j, $k, $m);
					endfor;
				endfor;
			endfor;
		endfor;

<a name="php-indentation"></a>
### 6.2 Indentation\*

For indentation, all code should follow strict [1TBS](http://en.wikipedia.org/wiki/Indent_style#Variant:_1TBS).

Tabs are preferred over spaces for indentation. Tab size should be controlled by the preferences in the text editor of whoever is writing/editing the code.

It is preferred that the values of object properties not be tabbed to align. __i.e.__

		// Incorrect.
			$badArray = [
				'oneProperty' => 		'value',
				'anotherProperty' =>	'value'
			]

		// Correct.
			$goodArray = [
				'oneProperty' => 'value',
				'anotherProperty' => 'value'
			]

<a name="php-brace-usage"></a>
### 6.3 Brace Usage
For Functions, Classes, Traits and Interfaces, the opening brace shoul be on same line as name. __i.e.__

		function myFunction($param) {
			//statements			
		}

		Interface myInterface {
			//statements			
		}

		Trait myTrait {
			//statements			
		}

		Class myClass Extends anotherClass Implements myInterface {
			use myTrait;
			//statements			
		}

If your  `if()`  or loop only contains one executable statement, the containing braces are optional, and should be omitted. __i.e.__

		if(expression)
			//statement

		// or

		foreach($array as $key => $value)
			// statement

Braces should not be used for blocks  `if`, `switch`, `while`, `for` and `foreach`, Use the form block: endblock; __i.e.__

		if($foo === $bar):
			$foo++;
		endif;

		for($i = 0; $i < 10; $i++):
			$foo[$i] = $bar;
		endfor;

		foreach($array as $index => $element):
			foo[$index] = $element;
		endforeach;

		while($value !== 0):
			$value--;
		endwhile;

		switch($value):
			case 1:
				//statement
			break;
			default:
				//statement
			break;
		endswitch;

The statement should still be indented as usual, and kept on a separate line for readability.

<a name="php-code-commenting"></a>
### 6.4 Code Commenting
It's best practice if code is sufficiently commented to aid future developers who may have to pick it up. At the very least, all functions and class methods should be commented in the [DocBlock](http://phpdoc.org/docs/latest/glossary.html#term-docblocks) format, and should contain a brief description about that function, and details on the inputs and outputs. A decent IDE should be able to automatically supply a lot of these details for you when you open the DocBlock with `/**` and hit enter.

__i.e.__

	/**
		* return a filtered string
		* @param string $filter_line the string to be filtered
		* @param string $replace_char optional character to use as the replacement - defaults to *
		* @return string
	*/ 
	public function filter_string($filter_line, $replace_char='*') {
	...

<a name="php-white-space"></a>
### 6.5 White Space
Avoid this if you can. Most IDEs and advanced text editors have an option to automatically strip trailing whitespace upon saving of a file, and you should enable this option if it exists.

<a name="php-line-length"></a>
### 6.6 Line Length\*

Lines should ideally be less than 80 characters. In rare cases, longer lines are acceptable but should never exceed 120 characters. Following the line length standard not only improves the code's readability, but also helps enforce good, clean code.

When concatenating strings or dealing with chained methods, break them into multiple lines for improved readability. Use indentation to convey hierarchy. __i.e.__

		$object
			->method1()
			->returnedMethod2()
				->returnedAttribute;

When concatenating, try to avoid it, use interpolation and [Nowdoc](http://php.net/manual/en/language.types.string.php) instead.
<a name="php-line-spacing"></a>
### 6.7 Line Spacing\*

Use a blank line between logical breaks in your code. Multiple variable declarations should not have a blank line between them. Use a blank line before and after a control statement. Constants and variables, should be grouped and separated by a blank line.

		Class myClass Extends anotherClass Implements myInterface {
			use myTrait;
			
			public $property1;		
			public $property2;		
			public $property3;
			
			private $privateProp1;			
			private $privateProp2;			
			private $privateProp3;
			
			public function myFunction() {
				//sentences
			}	
		}

<a name="php-function-method-length"></a>
### 6.8 Function & Method Length\*

Functions and methods should do one thing and one thing only. It's been suggested that functions and methods be no longer than four lines of code. That is ideal, but not always possible. Regardless, the number of lines in your methods and functions will be short if they only do one thing. The best way to tell if your function only does one thing is to abstract until you can not abstract any more.

<a name="php-ternary-operators"></a>
### 6.9 Ternary Operators\*

Ternary operators are allowed only where logical and efficient. However, you should __never__ have nested ternary operators. Ternary conditions should be wrapped in parenthesis for readability, even if it's just a single conditional. Ternary operators may also be broken up into multiple lines if it makes sense to do so. When broken into multiple lines, the "?" and ":" operators should line up and be indented one tab further. __i.e.__

		$a = ($b === $c and b === d)
			? $thisNameIsReallyReallyRidiculouslyLong
			: $notQuiteAsLong;

There is another choice in order to approach efficient logic: using non-void returned functions with condittional operatos:

		empty($var) and print('Empty var');
		
		// this is also correct
		empty($var) and ($var = 1);

<a name="php-html-output"></a>
### 6.10 Models\*

The models should contain logic as much as possible. Always will handle data-manipulation methods and event-based callbacks. __i.e.__

	Class User extends ActiveRecord {
		public $before_insert = array('encryptPassword');
		 
		public function encryptPassword() {
			$this->password = md5($this->password);
		}
	}

Or, this way is acceptable too:

	Class User extends ActiveRecord {
		public function init() {
			$this->before_insert = array('encryptPassword');
		}
		 
		public function encryptPassword() {
			$this->password = md5($this->password);
		}
	}

Any data-model manipulation on controllers is not allowed, unless extremly needed.

<a name="php-mvc-views"></a>
### 6.11 Views\*

If is possible, avoid HTML output via `echo` or `print` unless you have prebuilded HTML; always use php script insertion. __i.e.__

		<table>
		<? foreach($this->users as $user): ?>
		<tr>
			<td><?=$user->name;?></td>
		</tr>
		<? endforeach; ?>
		</table>

Use always short open tags and the simplified echo `<?=$var;?>`.

The file naming should use the [Dumbo PHP Framework](http://www.dumbophp.com/) standards.
<a name="php-mvc-controllers"></a>
### 6.12 Controllers\*

The controllers should handle logic for the navigation flow. The controllers folder will contain Traits and additional clases for use and inclusion into the controllers.

The file naming should use the [Dumbo PHP Framework](http://www.dumbophp.com/) standards.
<a name="php-mvc-helpers"></a>
### 6.13 Helpers\*

The helpers should only have functions for across controllers calls, like session handler.

The file naming should use the [Dumbo PHP Framework](http://www.dumbophp.com/) standards.

<a name="php-mvc-file-structure"></a>
### 6.14 File Structure

The entire project should follow this structure.

	|-- -js                     // JS source files
    |-- .ant                    // Files for jenkins tasks
    |-- app                     // MVC - App logic
    |   |-- controllers         // MVC - Controllers
    |   |-- helpers             // MVC - Helpers functions for controllers
    |   |-- models              // MVC - Models
    |   |-- views               // MVC - Views
    |   |-- webroot             // Exposed content to everyone (Assets)
    |       |-- css             // CSS compiled files
    |       |-- fonts           // Fonts used in the proyect
    |       |-- images          // Proyect images
    |       |-- js              // JS compiled files
    |       |-- plugins         // Third-party JS script compound by many kind of files
    |-- config                  // Config proyect files (db_settings, host)
    |-- migrations              // MVC - Migrations files (dumps, seeds, creations)
    |-- sass                    // Compass files in scss format
    |-- vendors                 // PHP clases, snippets, etc. not owned by us

<a name="git"></a>
## 7. GIT

<a name="git-commits"></a>
### 7.1 Commits

You should commit frequently and in logical increments. Commit messages should be clear and informative.

<a name="code-completeness"></a>
## 8. Code Completeness\*

Code will not be considered complete until it meets all standards and matches design comps with acceptable pixel accuracy. This means that all pages are identical to the designs including color, font size, font weight, etc. The only exceptions are adjustments in mis-calculated spacing, non-pixel increments, and alignment with the grid. No JS containing console.log() or alert() will be considered complete.

<a name="signature"></a>
__I agree to maintain these standards over the duration of the project.__

__Archaeopterix-1.0__

	Javier Serrano
