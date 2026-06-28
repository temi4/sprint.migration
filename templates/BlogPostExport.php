<?php

/**
 * @var $version
 * @var $description
 * @var $blog
 * @var $posts
 * @var $extendUse
 * @var $extendClass
 * @var $moduleVersion
 * @var $author
 * @formatter:off
 */

?><?php echo "<?php\n" ?>

namespace Sprint\Migration;

<?php echo $extendUse ?>

class <?php echo $version ?> extends <?php echo $extendClass ?>

{
    protected $author = "<?php echo $author ?>";

    protected $description = "<?php echo $description ?>";

    protected $moduleVersion = "<?php echo $moduleVersion ?>";

    /**
     * @throws Exceptions\HelperException
     * @return bool|void
     */
    public function up()
    {
        $helper = $this->getHelperManager();
        $exchangeDir = $this->getVersionConfig()->getVersionExchangeDir($this->getVersionName());

        $blogId = $helper->Blog()->getBlogId(<?php echo var_export($blog['URL'], 1) ?>);
        if (!$blogId) {
            throw new Exceptions\HelperException('Blog <?php echo addslashes($blog['URL']) ?> not found');
        }

<?php foreach ($posts as $post) { ?>
        $helper->Blog()->savePost($blogId, <?php echo var_export($post, 1) ?>, $exchangeDir);
<?php } ?>
    }
}
