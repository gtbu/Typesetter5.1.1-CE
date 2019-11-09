<?php

/**
 * Test the gp\tool\Archive class
 *
 */
class phpunit_Archive extends gptest_bootstrap{

	private $dir;
	private $types		= array('tbz','tgz','tar','zip','tar.bz','tar.gz');
	private $files		= array(
							'index.html'					=> '<html><body></body></html>',
							'foo/text.txt'					=> 'lorem ipsum',
							'foo/index.html'				=> '<html><body></body></html>',
							'/foo/bar'						=> 'foo bar',
							'foo/unicode/index.html' 		=> '<html><body></body></html>',

							// unicode isn't supported by pharData until php 5.4.29/5.5.13/5.6.0
							//'foo/unicode/Kødpålæg.tst'		=> 'Die style.css hatte ich an dieser Stelle zuvor nicht überarbeitet.',
							);

	/**
	 * Create the files and folders
	 *
	 */
	function setUp(){

		// HHVM doesn't support writing with PHAR
		// https://github.com/facebook/hhvm/issues/4899
		if( defined('HHVM_VERSION') ){
			$this->types = array('zip');
		}


		$this->dir		= sys_get_temp_dir().'/test-'.rand(1,10000000);

		foreach($this->files as $name => $content){
			$full = $this->dir.'/'.$name;
			\gp\tool\Files::Save($full,$content);
		}
	}


	/**
	 * Test creation
	 *
	 */
	function testCreate(){

		foreach($this->types as $type){
			$archive = $this->FromFiles($type);
			$list = $archive->ListFiles();
			self::AssertEquals( count($this->files), $archive->Count() );
		}

	}


	/**
	 * Test archive creation from string
	 *
	 */
	function testCreateString(){
		foreach($this->types as $type){
			$archive = $this->FromString($type);
			self::AssertEquals( count($this->files), $archive->Count() );
		}
	}


	/**
	 * Extract from a tar archive
	 *
	 */
	function testExtract(){

		foreach($this->types as $type){

			$archive	= $this->FromString($type);

			foreach($this->files as $name => $content){
				$extracted	= $archive->getFromName($name);
				self::AssertEquals($content, $extracted );
			}
		}
	}


	/**
	 * Test ListFiles()
	 *
	 */
	function testListFiles(){

		foreach($this->types as $type){
			$archive	= $this->FromString($type);
			$list		= $archive->ListFiles();
			self::AssertEquals( count($list), count($this->files) );
		}
	}


	/**
	 * Test GetRoot()
	 *
	 */
	function testGetRoot(){

		foreach($this->types as $type){
			$archive	= $this->FromString($type);
			$root		= $archive->GetRoot('text.txt');
			self::AssertEquals( 'foo', $root );
		}
	}


	/**
	 * Create an archive, add a file using AddFromString()
	 *
	 */
	function FromString($type){

		$path = $this->ArchivePath($type);

		try{
			$archive	= new \gp\tool\Archive($path);
			foreach($this->files as $name => $content){
				$archive->addFromString($name, $content);
			}
		}catch( Exception $e){
			self::AssertTrue( false, 'FromString('.$type.') Failed with message: '.$e->getMessage() );
			return;
		}

		$archive->Compress();
		self::AssertFileExists( $path );


		//return a readable archive
		$path2 = $this->ArchivePath($type);
		copy($path,$path2);

		return new \gp\tool\Archive($path2);
	}


	/**
	 * Create archive from files
	 *
	 */
	function FromFiles($type){

		$path = $this->ArchivePath($type);

		try{
			$archive	= new \gp\tool\Archive($path);
			$archive->Add($this->dir);

		}catch( Exception $e){
			self::AssertTrue( false, 'FromFiles('.$type.') Failed with message: '.$e->getMessage() );
			return;
		}

		$archive->Compress();
		self::AssertFileExists( $path );

		return new \gp\tool\Archive($path); //return a readable archive
	}



	function ArchivePath($type){
		return sys_get_temp_dir().'/archive-'.rand(0,100000).'.'.$type;
	}


}
