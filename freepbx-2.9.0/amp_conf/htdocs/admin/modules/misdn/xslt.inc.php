<?php
/*
 * freepbx-misdn -- mISDN module for FreePBX
 *
 * Copyright (C) 2006, Thomas Liske.
 *
 * Thomas Liske <thomas.liske@beronet.com>
 *
 * This program is free software, distributed under the terms of
 * the GNU General Public License Version 2.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 */

define('XSLTPROC', '/usr/bin/xsltproc');

/* The XSLT Functions API is not available in PHP 5 - emulate it
 * using the new XSL Functions API (if available).
 *
 * This is taken from the PHP Manual user comments.
  */
if (class_exists('XsltProcessor') && !function_exists('xslt_create')) {
    function xslt_create() {
	return new XsltProcessor();
    }

    function xslt_process($xsltproc, $xml_arg, $xsl_arg, $xslcontainer = null, $args = null, $params = null) {
	$xml = new DomDocument;
	if (substr($xml_arg,0,4) == 'arg:') {
	    $xml_arg = str_replace('arg:', '', $xml_arg);
	    $xml->loadXML($args[$xml_arg]);
	}
	else
	    $xsl->load($xml_arg);
	
	$xsl = new DomDocument;
	if (substr($xsl_arg,0,4) == 'arg:') {
	    $xsl_arg = str_replace('arg:', '', $xsl_arg);
	    $xsl->loadXML($args[$xsl_arg]);
	}
	else
	    $xsl->load($xsl_arg);

	$xsltproc->importStyleSheet($xsl);

	if ($params) {
    	    foreach ($params as $param => $value) {
        	$xsltproc->setParameter("", $param, $value);
    	    }
	}

	$processed = $xsltproc->transformToXML($xml);

	if ($xslcontainer) {
	    return @file_put_contents($xslcontainer, $processed);
	} else {
	    return $processed;
	}
    }

    function xslt_free($xsltproc) {
	unset($xsltproc);
    }
}

/* Maybe there is no XSL Functions API nor the XSLT Functions API available,
 * so fallback running xsltproc from command line if possible */
if (!function_exists('xslt_create') && is_executable(XSLTPROC)) {
    function xslt_create() {
	return 1;
    }

    function xslt_process($xsltproc, $xml_arg, $xsl_arg, $xslcontainer = null, $args = null, $params = null) {
	if (substr($xml_arg,0,4) == 'arg:') {
	    $xml_arg = str_replace('arg:', '', $xml_arg);
	    $xmlfile = tempnam("/tmp", 'xml');
	    file_put_contents($xmlfile, $args[$xml_arg]);
	}
	else
	    $xmlfile = $xml_arg;

	if (substr($xsl_arg,0,4) == 'arg:') {
	    $xsl_arg = str_replace('arg:', '', $xsl_arg);
	    $xslfile = tempnam("/tmp", 'xsl');
	    file_put_contents($xslfile, $args[$xsl_arg]);
	}
	else
	    $xslfile = $xsl_arg;
	    
	$_params = '';
	if ($params) {
    	    foreach ($params as $param => $value) {
		$_params .= ' --param "'.EscapeShellCmd($param).'" "'.EscapeShellCmd($value).'"';
    	    }
	}

	$processed = '';
        $fd = popen(XSLTPROC."$_params \"".EscapeShellCmd($xslfile).'" "'.EscapeShellCmd($xmlfile).'" 2> /dev/null', 'r');
	while($line = fgets($fd)) $processed .= $line;
        pclose($fd);

	if ($xml_arg != $xmlfile)
	    unlink($xmlfile);
	if ($xsl_arg != $xslfile)
	    unlink($xslfile);

	if ($xslcontainer) {
	    return @file_put_contents($xslcontainer, $processed);
	} else {
	    return $processed;
	}
    }

    function xslt_free($xsltproc) {
    }
}
