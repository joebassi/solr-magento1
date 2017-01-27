<?php
/**
 * integer_net Magento Module
 *
 * @category   IntegerNet
 * @package    IntegerNet_Solr
 * @copyright  Copyright (c) 2015 integer_net GmbH (http://www.integer-net.de/)
 * @author     Fabian Schmengler <fs@integer-net.de>
 */
use IntegerNet\Solr\Exception;
use IntegerNet\Solr\Resource\ResourceFacade;

/**
 * @loadFixture registry
 * @loadFixture config
 */
class IntegerNet_Solr_Test_Model_Indexer_Product extends IntegerNet_Solr_Test_Controller_Abstract
{
    /**
     * @param array $config
     * @test
     * @dataProvider dataProvider
     * @dataProviderFile invalid-config.yaml
     * @expectedException Exception
     * @expectedExceptionMessage Configuration Error
     */
    public function invalidSwapConfigurationShouldThrowException(array $config)
    {
        foreach (Mage::app()->getStores(true) as $store) {
            $store->resetConfig();
        }
        foreach ($config as $path => $value) {
            Mage::getConfig()->setNode($path, $value);
        }
        Mage::helper('integernet_solr')->factory()->getProductIndexer()->reindex();
    }

    /**
     * @test
     * @loadFixture catalog
     */
    public function saveProductShouldUpdateSolrIndex()
    {
        $this->setUpFreshIndex();

        $this->setCurrentStore(0);
        $this->adminSession();
        $productId = 21001;
        $product = Mage::getModel('catalog/product')->load($productId);
        $product->setData('name', 'SUPERDUPER');
        $product->save();
        $searchResponse = $this->searchFor('SUPERDUPER');
        $this->assertCount(1, $searchResponse->documents());
    }

    /**
     * @param $queryText
     * @return \IntegerNet\Solr\Resource\SolrResponse
     */
    public function searchFor($queryText)
    {
        $storeId = 1;
        $queryStub = $this->getMockBuilder(\IntegerNet\Solr\Implementor\HasUserQuery::class)
            ->getMockForAbstractClass();
        $queryStub->method('getUserQueryText')->willReturn($queryText);
        $factory = Mage::helper('integernet_solr/factory');
        $applicationContext = $factory->getApplicationContext();
        $applicationContext->setFuzzyConfig($factory->getCurrentStoreConfig()->getFuzzySearchConfig());
        $applicationContext->setQuery($queryStub);
        $applicationContext->setPagination(new \IntegerNet\Solr\Request\SinglePage(2));
        $searchRequestFactory = new \IntegerNet\Solr\Request\SearchRequestFactory(
            $applicationContext,
            $factory->getSolrResource(),
            $storeId
        );
        return $searchRequestFactory->createRequest()->doRequest();
    }

    private function setUpFreshIndex()
    {
        Mage::getModel('integernet_solr/indexer')->reindexAll();
        $indexProcess = Mage::getModel('index/process')->load('integernet_solr', 'indexer_code');
        $indexProcess->setMode(Mage_Index_Model_Process::MODE_REAL_TIME)->save();
    }
}