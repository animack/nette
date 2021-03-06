<?php

/**
 * This file is part of the Nette Framework (http://nette.org)
 *
 * Copyright (c) 2004 David Grudl (http://davidgrudl.com)
 *
 * For the full copyright and license information, please view
 * the file license.txt that was distributed with this source code.
 */

namespace Nette\Latte\Macros;

use Nette,
	Nette\Latte,
	Nette\Latte\CompileException,
	Nette\Latte\MacroNode,
	Nette\Latte\PhpWriter;


/**
 * Basic macros for Latte.
 *
 * - {if ?} ... {elseif ?} ... {else} ... {/if}
 * - {ifset ?} ... {elseifset ?} ... {/ifset}
 * - {for ?} ... {/for}
 * - {foreach ?} ... {/foreach}
 * - {$variable} with escaping
 * - {!$variable} without escaping
 * - {=expression} echo with escaping
 * - {!=expression} echo without escaping
 * - {?expression} evaluate PHP statement
 * - {_expression} echo translation with escaping
 * - {!_expression} echo translation without escaping
 * - {attr ?} HTML element attributes
 * - {capture ?} ... {/capture} capture block to parameter
 * - {var var => value} set template parameter
 * - {default var => value} set default template parameter
 * - {dump $var}
 * - {debugbreak}
 * - {l} {r} to display { }
 *
 * @author     David Grudl
 */
class CoreMacros extends MacroSet
{


	public static function install(Latte\Compiler $compiler)
	{
		$me = new static($compiler);

		$me->addMacro('if', array($me, 'macroIf'), array($me, 'macroEndIf'));
		$me->addMacro('elseif', '} elseif (%node.args) {');
		$me->addMacro('else', array($me, 'macroElse'));
		$me->addMacro('ifset', 'if (isset(%node.args)) {', '}');
		$me->addMacro('elseifset', '} elseif (isset(%node.args)) {');
		$me->addMacro('ifcontent', array($me, 'macroIfContent'), array($me, 'macroEndIfContent'));

		$me->addMacro('switch', '$_l->switch[] = (%node.args); if (FALSE) {', '} array_pop($_l->switch)');
		$me->addMacro('case', '} elseif (end($_l->switch) === (%node.args)) {');

		$me->addMacro('foreach', '', array($me, 'macroEndForeach'));
		$me->addMacro('for', 'for (%node.args) {', '}');
		$me->addMacro('while', 'while (%node.args) {', '}');
		$me->addMacro('continueIf', array($me, 'macroBreakContinueIf'));
		$me->addMacro('breakIf', array($me, 'macroBreakContinueIf'));
		$me->addMacro('first', 'if ($iterator->isFirst(%node.args)) {', '}');
		$me->addMacro('last', 'if ($iterator->isLast(%node.args)) {', '}');
		$me->addMacro('sep', 'if (!$iterator->isLast(%node.args)) {', '}');

		$me->addMacro('var', array($me, 'macroVar'));
		$me->addMacro('default', array($me, 'macroVar'));
		$me->addMacro('dump', array($me, 'macroDump'));
		$me->addMacro('debugbreak', array($me, 'macroDebugbreak'));
		$me->addMacro('l', '?>{<?php');
		$me->addMacro('r', '?>}<?php');

		$me->addMacro('_', array($me, 'macroTranslate'), array($me, 'macroTranslate'));
		$me->addMacro('=', array($me, 'macroExpr'));
		$me->addMacro('?', array($me, 'macroExpr'));

		$me->addMacro('capture', array($me, 'macroCapture'), array($me, 'macroCaptureEnd'));
		$me->addMacro('include', array($me, 'macroInclude'));
		$me->addMacro('use', array($me, 'macroUse'));

		$me->addMacro('class', NULL, NULL, array($me, 'macroClass'));
		$me->addMacro('attr', NULL, NULL, array($me, 'macroAttr'));
		$me->addMacro('href', NULL); // TODO: placeholder
	}


	/**
	 * Finishes template parsing.
	 * @return array(prolog, epilog)
	 */
	public function finalize()
	{
		return array('list($_l, $_g) = Nette\Latte\Macros\CoreMacros::initRuntime($template, '
			. var_export($this->getCompiler()->getTemplateId(), TRUE) . ')');
	}


	/********************* macros ****************d*g**/


	/**
	 * {if ...}
	 */
	public function macroIf(MacroNode $node, PhpWriter $writer)
	{
		if ($node->data->capture = ($node->args === '')) {
			return 'ob_start()';
		}
		if ($node->prefix === $node::PREFIX_TAG) {
			return $writer->write($node->htmlNode->closing ? 'if (array_pop($_l->ifs)) {' : 'if ($_l->ifs[] = (%node.args)) {');
		}
		return $writer->write('if (%node.args) {');
	}


	/**
	 * {/if ...}
	 */
	public function macroEndIf(MacroNode $node, PhpWriter $writer)
	{
		if ($node->data->capture) {
			if ($node->args === '') {
				throw new CompileException('Missing condition in {if} macro.');
			}
			return $writer->write('if (%node.args) '
				. (isset($node->data->else) ? '{ ob_end_clean(); ob_end_flush(); }' : 'ob_end_flush();')
				. ' else '
				. (isset($node->data->else) ? '{ $_else = ob_get_contents(); ob_end_clean(); ob_end_clean(); echo $_else; }' : 'ob_end_clean();')
			);
		}
		return '}';
	}


	/**
	 * {else}
	 */
	public function macroElse(MacroNode $node, PhpWriter $writer)
	{
		$ifNode = $node->parentNode;
		if ($ifNode && $ifNode->name === 'if' && $ifNode->data->capture) {
			if (isset($ifNode->data->else)) {
				throw new CompileException("Macro {if} supports only one {else}.");
			}
			$ifNode->data->else = TRUE;
			return 'ob_start()';
		}
		return '} else {';
	}


	/**
	 * n:ifcontent
	 */
	public function macroIfContent(MacroNode $node, PhpWriter $writer)
	{
		if (!$node->htmlNode) {
			throw new CompileException("Unknown macro {{$node->name}}, use n:{$node->name} attribute.");
		} elseif ($node->prefix !== MacroNode::PREFIX_NONE) {
			throw new CompileException("Unknown attribute n:{$node->prefix}-{$node->name}, use n:{$node->name} attribute.");
		}

		return $writer->write('ob_start()');
	}


	/**
	 * n:ifcontent
	 */
	public function macroEndIfContent(MacroNode $node, PhpWriter $writer)
	{
		preg_match('#(^.*?>)(.*)(<.*\z)#s', $node->content, $parts);
		$node->content = $parts[1]
			. '<?php ob_start() ?>'
			. $parts[2]
			. '<?php $_ifcontent = ob_get_length(); ob_end_flush() ?>'
			. $parts[3];
		return '$_ifcontent ? ob_end_flush() : ob_end_clean()';
	}


	/**
	 * {_$var |modifiers}
	 */
	public function macroTranslate(MacroNode $node, PhpWriter $writer)
	{
		if ($node->closing) {
			return $writer->write('echo %modify($template->translate(ob_get_clean()))');

		} elseif ($node->isEmpty = ($node->args !== '')) {
			return $writer->write('echo %modify($template->translate(%node.args))');

		} else {
			return 'ob_start()';
		}
	}


	/**
	 * {include "file" [,] [params]}
	 */
	public function macroInclude(MacroNode $node, PhpWriter $writer)
	{
		$code = $writer->write('Nette\Latte\Macros\CoreMacros::includeTemplate(%node.word, %node.array? + $template->getParameters(), $_l->templates[%var])',
			$this->getCompiler()->getTemplateId());

		if ($node->modifiers) {
			return $writer->write('echo %modify(%raw->__toString(TRUE))', $code);
		} else {
			return $code . '->render()';
		}
	}


	/**
	 * {use class MacroSet}
	 */
	public function macroUse(MacroNode $node, PhpWriter $writer)
	{
		Nette\Utils\Callback::invoke(array($node->tokenizer->fetchWord(), 'install'), $this->getCompiler())
			->initialize();
	}


	/**
	 * {capture $variable}
	 */
	public function macroCapture(MacroNode $node, PhpWriter $writer)
	{
		$variable = $node->tokenizer->fetchWord();
		if (substr($variable, 0, 1) !== '$') {
			throw new CompileException("Invalid capture block variable '$variable'");
		}
		$node->data->variable = $variable;
		return 'ob_start()';
	}


	/**
	 * {/capture}
	 */
	public function macroCaptureEnd(MacroNode $node, PhpWriter $writer)
	{
		return $node->data->variable . $writer->write(" = %modify(ob_get_clean())");
	}


	/**
	 * {foreach ...}
	 */
	public function macroEndForeach(MacroNode $node, PhpWriter $writer)
	{
		if ($node->modifiers !== '|noiterator' && preg_match('#\W(\$iterator|include|require|get_defined_vars)\W#', $this->getCompiler()->expandTokens($node->content))) {
			$node->openingCode = '<?php $iterations = 0; foreach ($iterator = $_l->its[] = new Nette\Iterators\CachingIterator('
			. preg_replace('#(.*)\s+as\s+#i', '$1) as ', $writer->formatArgs(), 1) . ') { ?>';
			$node->closingCode = '<?php $iterations++; } array_pop($_l->its); $iterator = end($_l->its) ?>';
		} else {
			$node->openingCode = '<?php $iterations = 0; foreach (' . $writer->formatArgs() . ') { ?>';
			$node->closingCode = '<?php $iterations++; } ?>';
		}
	}


	/**
	 * {breakIf ...}
	 * {continueIf ...}
	 */
	public function macroBreakContinueIf(MacroNode $node, PhpWriter $writer)
	{
		$cmd = str_replace('If', '', $node->name);
		if ($node->parentNode && $node->parentNode->prefix === $node::PREFIX_NONE) {
			return $writer->write("if (%node.args) { echo \"</{$node->parentNode->htmlNode->name}>\\n\"; $cmd; }");
		}
		return $writer->write("if (%node.args) $cmd");
	}


	/**
	 * n:class="..."
	 */
	public function macroClass(MacroNode $node, PhpWriter $writer)
	{
		return $writer->write('if ($_l->tmp = array_filter(%node.array)) echo \' class="\' . %escape(implode(" ", array_unique($_l->tmp))) . \'"\'');
	}


	/**
	 * n:attr="..."
	 */
	public function macroAttr(MacroNode $node, PhpWriter $writer)
	{
		return $writer->write('echo Nette\Utils\Html::el(NULL, %node.array)->attributes()');
	}


	/**
	 * {dump ...}
	 */
	public function macroDump(MacroNode $node, PhpWriter $writer)
	{
		$args = $writer->formatArgs();
		return 'Nette\Diagnostics\Debugger::barDump(' . ($node->args ? "array(" . $writer->write('%var', $args) . " => $args)" : 'get_defined_vars()')
			. ', "Template " . str_replace(dirname(dirname($template->getFile())), "\xE2\x80\xA6", $template->getFile()))';
	}


	/**
	 * {debugbreak ...}
	 */
	public function macroDebugbreak(MacroNode $node, PhpWriter $writer)
	{
		return $writer->write(($node->args == NULL ? '' : 'if (!(%node.args)); else')
			. 'if (function_exists("debugbreak")) debugbreak(); elseif (function_exists("xdebug_break")) xdebug_break()');
	}


	/**
	 * {var ...}
	 * {default ...}
	 */
	public function macroVar(MacroNode $node, PhpWriter $writer)
	{
		if ($node->args === '' && $node->parentNode && $node->parentNode->name === 'switch') {
			return '} else {';
		}

		$var = TRUE;
		$tokens = $writer->preprocess();
		$res = new Latte\MacroTokens;
		while ($tokens->nextToken()) {
			if ($var && $tokens->isCurrent(Latte\MacroTokens::T_SYMBOL, Latte\MacroTokens::T_VARIABLE)) {
				if ($node->name === 'default') {
					$res->append("'" . ltrim($tokens->currentValue(), '$') . "'");
				} else {
					$res->append('$' . ltrim($tokens->currentValue(), '$'));
				}
				$var = NULL;

			} elseif ($tokens->isCurrent('=', '=>') && $tokens->depth === 0) {
				$res->append($node->name === 'default' ? '=>' : '=');
				$var = FALSE;

			} elseif ($tokens->isCurrent(',') && $tokens->depth === 0) {
				$res->append($node->name === 'default' ? ',' : ';');
				$var = TRUE;

			} elseif ($var === NULL && $node->name === 'default' && !$tokens->isCurrent(Latte\MacroTokens::T_WHITESPACE)) {
				throw new CompileException("Unexpected '" . $tokens->currentValue() . "' in {default $node->args}");

			} else {
				$res->append($tokens->currentToken());
			}
		}
		$out = $writer->quoteFilter($res)->joinAll();
		return $node->name === 'default' ? "extract(array($out), EXTR_SKIP)" : $out;
	}


	/**
	 * {= ...}
	 * {? ...}
	 */
	public function macroExpr(MacroNode $node, PhpWriter $writer)
	{
		return $writer->write(($node->name === '?' ? '' : 'echo ') . '%modify(%node.args)');
	}


	/********************* run-time helpers ****************d*g**/


	/**
	 * Includes subtemplate.
	 * @param  mixed      included file name or template
	 * @param  array      parameters
	 * @param  Nette\Templating\ITemplate  current template
	 * @return Nette\Templating\Template
	 */
	public static function includeTemplate($destination, array $params, Nette\Templating\ITemplate $template)
	{
		if ($destination instanceof Nette\Templating\ITemplate) {
			$tpl = $destination;

		} elseif ($destination == NULL) { // intentionally ==
			throw new Nette\InvalidArgumentException("Template file name was not specified.");

		} elseif ($template instanceof Nette\Templating\IFileTemplate) {
			if (substr($destination, 0, 1) !== '/' && substr($destination, 1, 1) !== ':') {
				$destination = dirname($template->getFile()) . '/' . $destination;
			}
			$tpl = clone $template;
			$tpl->setFile($destination);

		} else {
			throw new Nette\NotSupportedException('Macro {include "filename"} is supported only with Nette\Templating\IFileTemplate.');
		}

		$tpl->setParameters($params); // interface?
		return $tpl;
	}


	/**
	 * Initializes local & global storage in template.
	 * @return \stdClass
	 */
	public static function initRuntime(Nette\Templating\ITemplate $template, $templateId)
	{
		// local storage
		if (isset($template->_l)) {
			$local = $template->_l;
			unset($template->_l);
		} else {
			$local = new \stdClass;
		}
		$local->templates[$templateId] = $template;

		// global storage
		if (!isset($template->_g)) {
			$template->_g = new \stdClass;
		}

		return array($local, $template->_g);
	}

}
