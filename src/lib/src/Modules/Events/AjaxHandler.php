<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Events;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Events;
use FernleafSystems\Wordpress\Services\Services;

class AjaxHandler extends Shield\Modules\BaseShield\AjaxHandler {

	protected function processAjaxAction( string $action ) :array {

		switch ( $action ) {
			case 'render_chart':
				$aResponse = $this->ajaxExec_RenderChart();
				break;

			case 'render_chart_post':
				$aResponse = $this->ajaxExec_RenderChartPost();
				break;

			default:
				$aResponse = parent::processAjaxAction( $action );
		}

		return $aResponse;
	}

	private function ajaxExec_RenderChart() :array {
		/** @var ModCon $mod */
		$mod = $this->getMod();

		$aParams = $this->getAjaxFormParams();
		$oReq = new Events\Charts\ChartRequestVO();
		$oReq->render_location = $aParams[ 'render_location' ];
		$oReq->chart_params = $aParams[ 'chart_params' ];
		$aChart = ( new Events\Charts\BuildData() )
			->setMod( $mod )
			->build( $oReq );

		return [
			'success' => true,
			'message' => 'no message',
			'chart'   => $aChart
		];
	}

	private function ajaxExec_RenderChartPost() :array {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		$req = Services::Request();
		$oChartReq = new Events\Charts\ChartRequestVO();
		$oChartReq->render_location = $req->post( 'render_location' );
		$oChartReq->chart_params = $req->post( 'chart_params' );

		$aChart = ( new Events\Charts\BuildData() )
			->setMod( $mod )
			->build( $oChartReq );

		return [
			'success' => true,
			'message' => 'no message',
			'chart'   => $aChart
		];
	}
}