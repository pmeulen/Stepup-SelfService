<?php

/**
 * Copyright 2014 SURFnet bv
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

namespace Surfnet\StepupSelfService\SelfServiceBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Surfnet\StepupSelfService\SelfServiceBundle\Service\SecondFactorService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class RegistrationController extends Controller
{
    /**
     * @Template
     */
    public function displaySecondFactorTypesAction()
    {
        $enabledSecondFactors = $this->getParameter('ss.enabled_second_factors');

        return [
            'commonName' => $this->getIdentity()->commonName,
            'enabledSecondFactors' => array_combine($enabledSecondFactors, $enabledSecondFactors),
            'tiqrAppAndroidUrl' => $this->getParameter('tiqr_app_android_url'),
            'tiqrAppIosUrl'     => $this->getParameter('tiqr_app_ios_url'),
        ];
    }

    /**
     * @Template
     */
    public function emailVerificationEmailSentAction()
    {
        return ['email' => $this->getIdentity()->email];
    }

    /**
     * @Template
     *
     * @param Request $request
     * @return array|\Symfony\Component\HttpFoundation\RedirectResponse
     */
    public function verifyEmailAction(Request $request)
    {
        $nonce = $request->query->get('n', '');
        $identityId = $this->getIdentity()->id;

        /** @var SecondFactorService $service */
        $service = $this->get('surfnet_stepup_self_service_self_service.service.second_factor');

        $secondFactor = $service->findUnverifiedByVerificationNonce($identityId, $nonce);

        if ($secondFactor === null) {
            throw new NotFoundHttpException('No second factor can be verified using this URL.');
        }

        if ($service->verifyEmail($identityId, $nonce)) {
            return $this->redirectToRoute(
                'ss_registration_registration_email_sent',
                ['secondFactorId' => $secondFactor->id]
            );
        }

        return [];
    }

    /**
     * @param $secondFactorId
     * @return Response
     */
    public function registrationEmailSentAction($secondFactorId)
    {
        $identity = $this->getIdentity();

        $parameters = [
            'email'            => $identity->email,
            'registrationCode' => $this->get('surfnet_stepup_self_service_self_service.service.second_factor')
                ->getRegistrationCode($secondFactorId, $identity->id),
        ];

        $raService         = $this->get('self_service.service.ra');
        $raLocationService = $this->get('self_service.service.ra_location');

        $institutionConfigurationOptions = $this->get('self_service.service.institution_configuration_options')
            ->getInstitutionConfigurationOptionsFor($identity->institution);

        if ($institutionConfigurationOptions->useRaLocations) {
            $parameters['raLocations'] = $raLocationService->listRaLocationsFor($identity->institution);
        } elseif (!$institutionConfigurationOptions->showRaaContactInformation) {
            $parameters['ras'] = $raService->listRasWithoutRaas($identity->institution);
        } else {
            $parameters['ras'] = $raService->listRas($identity->institution);
        }

        return $this->render(
            'SurfnetStepupSelfServiceSelfServiceBundle:Registration:registrationEmailSent.html.twig',
            $parameters
        );
    }
}
