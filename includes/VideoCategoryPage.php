<?php
/**
 * VideoHooks::categoryPageWithVideo(), which is hooked to the 'CategoryPageView' hook,
 * fires this class instead of the regular CategoryPage for NS_CATEGORY page views.
 * This class in turn fires up our custom renderer class, which ensures that videos
 * get output correctly.
 * This is somewhat convoluted and kinda stupid but necessary to ensure that the
 * viewer class is initialized with the correct parameters.
 *
 * @see https://phabricator.wikimedia.org/T276954
 */
class VideoCategoryPage extends CategoryPage {
	/** @var CategoryWithVideoViewer Override the viewer class. */
	// @phan-suppress-next-line PhanTypeMismatchPropertyDefault
	protected $mCategoryViewerClass = CategoryWithVideoViewer::class;
}
