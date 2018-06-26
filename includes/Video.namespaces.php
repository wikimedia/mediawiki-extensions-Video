<?php
/**
 * Translations of the Video namespace.
 *
 * @file
 */

$namespaceNames = [];

// For wikis where the Video extension is not installed.
if ( !defined( 'NS_VIDEO' ) ) {
	define( 'NS_VIDEO', 400 );
}

if ( !defined( 'NS_VIDEO_TALK' ) ) {
	define( 'NS_VIDEO_TALK', 401 );
}

/** English */
$namespaceNames['en'] = [
	NS_VIDEO => 'Video',
	NS_VIDEO_TALK => 'Video_talk',
];

/** German (Deutsch) */
$namespaceNames['de'] = [
	NS_VIDEO => 'Video',
	NS_VIDEO_TALK => 'Video_Diskussion',
];

/** Spanish (Español) */
$namespaceNames['es'] = [
	NS_VIDEO => 'Video',
	NS_VIDEO_TALK => 'Video_Discusión',
];

/** Estonian (Eesti) */
$namespaceNames['et'] = [
	NS_VIDEO => 'Video',
	NS_VIDEO_TALK => 'Video_arutelu',
];

/** Finnish (Suomi) */
$namespaceNames['fi'] = [
	NS_VIDEO => 'Video',
	NS_VIDEO_TALK => 'Keskustelu_videosta',
];

/** French (Français) */
$namespaceNames['fr'] = [
	NS_VIDEO => 'Vidéo',
	NS_VIDEO_TALK => 'Discussion_vidéo',
];

/** Italian (Italiano) */
$namespaceNames['it'] = [
	NS_VIDEO => 'Video',
	NS_VIDEO_TALK => 'Discussioni_video',
];

/** Korean (한국어) */
$namespaceNames['ko'] = [
	NS_VIDEO => '동영상',
	NS_VIDEO_TALK => '동영상토론',
];

/** Dutch (Nederlands) */
$namespaceNames['nl'] = [
	NS_VIDEO => 'Video',
	NS_VIDEO_TALK => 'Overleg_video',
];

/** Polish (Polski) */
$namespaceNames['pl'] = [
	NS_VIDEO => 'Video',
	NS_VIDEO_TALK => 'Dyskusja_Video',
];

/** Portuguese (Português) */
$namespaceNames['pt'] = [
	NS_VIDEO => 'Vídeo',
	NS_VIDEO_TALK => 'Vídeo_Discussão',
];

/** Russian (Русский) */
$namespaceNames['ru'] = [
	NS_VIDEO => 'Видео',
	NS_VIDEO_TALK => 'Обсуждение_видео'
];

/** Swedish (Svenska) */
$namespaceNames['sv'] = [
	NS_VIDEO => 'Video',
	NS_VIDEO_TALK => 'Videodiskussion',
];