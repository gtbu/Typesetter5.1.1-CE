<?php

require_once 'lessc.inc.php';

include  __DIR__ . '/lib/Parser.php';  
include  __DIR__ . '/lib/Colors.php'; 
include  __DIR__ . '/lib/Environment.php'; 
include  __DIR__ . '/lib/Functions.php'; 
include  __DIR__ . '/lib/Mime.php'; 
include  __DIR__ . '/lib/Tree.php'; 
include  __DIR__ . '/lib/Output.php'; 
include  __DIR__ . '/lib/Visitor.php'; 
include  __DIR__ . '/lib/VisitorReplacing.php';
include  __DIR__ . '/lib/Configurable.php'; 
// ------------------------------------Tree ----------------------------
include  __DIR__ . '/lib/Tree/Alpha.php'; 
include  __DIR__ . '/lib/Tree/Anonymous.php'; 
include  __DIR__ . '/lib/Tree/Assignment.php'; 
include  __DIR__ . '/lib/Tree/Attribute.php'; 
include  __DIR__ . '/lib/Tree/Call.php'; 
include  __DIR__ . '/lib/Tree/Color.php'; 
include  __DIR__ . '/lib/Tree/Comment.php'; 
include  __DIR__ . '/lib/Tree/Condition.php'; 
include  __DIR__ . '/lib/Tree/DefaultFunc.php';
include  __DIR__ . '/lib/Tree/DetachedRuleset.php'; 
include  __DIR__ . '/lib/Tree/Dimension.php'; 
include  __DIR__ . '/lib/Tree/Directive.php'; 
include  __DIR__ . '/lib/Tree/Element.php'; 
include  __DIR__ . '/lib/Tree/Expression.php'; 
include  __DIR__ . '/lib/Tree/Extend.php'; 
include  __DIR__ . '/lib/Tree/Import.php'; 
include  __DIR__ . '/lib/Tree/Javascript.php'; 
include  __DIR__ . '/lib/Tree/Keyword.php';
include  __DIR__ . '/lib/Tree/Media.php'; 
include  __DIR__ . '/lib/Tree/Negative.php'; 
include  __DIR__ . '/lib/Tree/NameValue.php'; 
include  __DIR__ . '/lib/Tree/Operation.php'; 
include  __DIR__ . '/lib/Tree/Paren.php';
include  __DIR__ . '/lib/Tree/Quoted.php';
include  __DIR__ . '/lib/Tree/Rule.php'; 
include  __DIR__ . '/lib/Tree/Ruleset.php'; 
include  __DIR__ . '/lib/Tree/RulesetCall.php'; 
include  __DIR__ . '/lib/Tree/Selector.php'; 
include  __DIR__ . '/lib/Tree/UnicodeDescriptor.php'; 
include  __DIR__ . '/lib/Tree/Unit.php'; 
include  __DIR__ . '/lib/Tree/UnitConversions.php';
include  __DIR__ . '/lib/Tree/Url.php'; 
include  __DIR__ . '/lib/Tree/Value.php';
include  __DIR__ . '/lib/Tree/Variable.php'; 
include  __DIR__ . '/lib/Tree/Mixin/Call.php'; 
include  __DIR__ . '/lib/Tree/Mixin/Definition.php'; 

include  __DIR__ . '/lib/Visitor/extendFinder.php'; 
include  __DIR__ . '/lib/Visitor/import.php'; 
include  __DIR__ . '/lib/Visitor/joinSelector.php'; 
include  __DIR__ . '/lib/Visitor/processExtends.php'; 
include  __DIR__ . '/lib/Visitor/toCss.php';
 
include  __DIR__ . '/lib/Exception/Parser.php'; 
include  __DIR__ . '/lib/Exception/Chunk.php'; 
include  __DIR__ . '/lib/Exception/Compiler.php'; 

include  __DIR__ . '/lib/Output/Mapped.php';

include  __DIR__ . '/lib/Sourcemap/Base64VLQ.php'; 
include  __DIR__ . '/lib/Sourcemap/Generator.php'; 
