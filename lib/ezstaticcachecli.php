<?php

// SOFTWARE NAME: CLI makestaticcache
// COPYRIGHT NOTICE: Copyright (C) 2007 Damien POBEL
// SOFTWARE LICENSE: GNU General Public License v2.0
// NOTICE: >
//   This program is free software; you can redistribute it and/or
//   modify it under the terms of version 2.0  of the GNU General
//   Public License as published by the Free Software Foundation.
//
//   This program is distributed in the hope that it will be useful,
//   but WITHOUT ANY WARRANTY; without even the implied warranty of
//   MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
//   GNU General Public License for more details.
//
//   You should have received a copy of version 2.0 of the GNU General
//   Public License along with this program; if not, write to the Free
//   Software Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston,
//   MA 02110-1301, USA.


include_once( 'kernel/classes/ezstaticcache.php' );


class eZStaticCacheCLI extends eZStaticCache
{

	function eZStaticCacheCLI()
	{
		$this->eZStaticCache();
	}

	function level($url)
	{
		if ( $url == '' )
			return 0;
		return substr_count( $url, '/' )+1;
	}

	function generateCache($force = false, $quiet = false, $cli = false, $subtree = '/', $maxLevel = 0)
	{
		$subtree = trim( $subtree, '/' );
		$subtreeMessage = $subtree;
		if ( $subtree == '/' )
		{
			$subtree = '';
			$subtreeMessage = '/';
		}
		$subtreeLevel = eZStaticCacheCLI::level( $subtree );

		if ( !$quiet && $cli )
			$cli->output( 'Using Subtree ' . $subtreeMessage . '  level: '.$subtreeLevel );
		$pageArray = array();
		if ( $subtreeLevel == $maxLevel )
		{
			// only the page indicated by $subtree
			$this->cacheURL( $subtree, !$force );
			if ( !$quiet && $cli )
				$cli->output( '  Caching ' . $subtree );
		}
		elseif ( $maxLevel > $subtreeLevel )
		{
			// a real subtree
			$db =& eZDB::instance();
			$queryLike = $db->escapeString( $subtree . '%' );
			$aliasArray = $db->arrayQuery( "SELECT source_url FROM ezurlalias
													WHERE source_url LIKE '$queryLike'
														AND source_url NOT LIKE '%*'
													ORDER BY source_url" );
			$urlCount = count( $aliasArray );
			$currentURL = 0;
			foreach( $aliasArray as $aliasInfo )
			{
				$currentURL++;
				$url = $aliasInfo['source_url'];
				$level = eZStaticCacheCLI::level( $url );
				if ( $level <= $maxLevel )
				{
					if ( !$quiet && $cli )
						$cli->output( sprintf("   %5.1f%% Caching $url", 100 * ($currentURL / $urlCount)));
					$this->cacheURL( $url, !$force );
				}
			}

		}
		else
		{
			// nothing to do
			return ;
		}

	}

	function cacheURL($url, $skipUnlink)
	{
        $hostname = $this->HostName;
        $staticStorageDir = $this->StaticStorageDir;
        if ( is_array( $this->CachedSiteAccesses ) and count ( $this->CachedSiteAccesses ) )
        {
            $dirs = array();
            foreach ( $this->CachedSiteAccesses as $dir )
                $dirs[] = '/' . $dir ;
        }
        else
            $dirs = array ('/');

        foreach ( $dirs as $dir )
        {
			$file = '';
            if ( !is_dir( $dir ) )
                eZDir::mkdir( $dir, 0777, true );

            $file = $this->buildCacheFilename( $staticStorageDir, $dir . $url );
			if ( !$skipUnlink || !file_exists( $file ) )
			{
				$fileName = "http://$hostname$dir$url";
				$content = eZStaticCacheCLI::fileGetContents( $fileName );
				if ( $content === false )
					eZDebug::writeNotice( 'Could not grab content, is the hostname correct and Apache running?', 'Static Cache' );
				else
					$this->storeCachedFile( $file, $content );
			}
        }
    }

	function fileGetContents( $url )
	{
		$fp = fopen( $url, 'r' );
		if ( ! $fp )
			return false;
		$meta_data = stream_get_meta_data( $fp );
		if ( ereg( '^HTTP/1.1 30[12]', $meta_data['wrapper_data'][0]) )
			return false;
		$tmp = '';
		$result = '';
		while ( ( $tmp = fgets( $fp, 4096 ) ) !== false )
			$result .= $tmp;
		return $result;
	}



}

?>
