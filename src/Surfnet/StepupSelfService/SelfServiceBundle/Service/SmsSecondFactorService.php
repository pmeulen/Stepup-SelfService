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

namespace Surfnet\StepupSelfService\SelfServiceBundle\Service;

use Surfnet\StepupMiddlewareClientBundle\Service\CommandService;
use Surfnet\StepupMiddlewareClientBundle\Uuid\Uuid;
use Surfnet\StepupSelfService\SelfServiceBundle\Command\SendSmsChallengeCommand;
use Surfnet\StepupSelfService\SelfServiceBundle\Command\SendSmsCommand;
use Surfnet\StepupSelfService\SelfServiceBundle\Command\VerifySmsChallengeCommand;
use Surfnet\StepupSelfService\SelfServiceBundle\Exception\InvalidArgumentException;
use Surfnet\StepupSelfService\SelfServiceBundle\Identity\Command\ProvePhonePossessionCommand;
use Surfnet\StepupSelfService\SelfServiceBundle\Service\Exception\TooManyChallengesRequestedException;
use Surfnet\StepupSelfService\SelfServiceBundle\Service\SmsSecondFactor\ChallengeHandler;
use Surfnet\StepupSelfService\SelfServiceBundle\Service\SmsSecondFactor\ProofOfPossessionResult;
use Symfony\Component\Translation\TranslatorInterface;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class SmsSecondFactorService
{
    /**
     * @var SmsService
     */
    private $smsService;

    /**
     * @var ChallengeHandler
     */
    private $challengeHandler;

    /**
     * @var TranslatorInterface
     */
    private $translator;

    /**
     * @var CommandService
     */
    private $commandService;

    /**
     * @var string
     */
    private $originator;

    /**
     * @param SmsService $smsService
     * @param ChallengeHandler $challengeHandler
     * @param TranslatorInterface $translator
     * @param CommandService $commandService
     * @param string $originator
     */
    public function __construct(
        SmsService $smsService,
        ChallengeHandler $challengeHandler,
        TranslatorInterface $translator,
        CommandService $commandService,
        $originator
    ) {
        if (!is_string($originator)) {
            throw InvalidArgumentException::invalidType('string', 'originator', $originator);
        }

        if (!preg_match('~^[a-z0-9]{1,11}$~i', $originator)) {
            throw new InvalidArgumentException(
                'Invalid SMS originator given: may only contain alphanumerical characters.'
            );
        }

        $this->smsService = $smsService;
        $this->challengeHandler = $challengeHandler;
        $this->translator = $translator;
        $this->commandService = $commandService;
        $this->originator = $originator;
    }

    /**
     * @param SendSmsChallengeCommand $command
     * @return bool Whether SMS sending did not fail.
     * @throws TooManyChallengesRequestedException
     */
    public function sendChallenge(SendSmsChallengeCommand $command)
    {
        $challenge = $this->challengeHandler->requestOtp($command->recipient);

        $body = $this->translator->trans('ss.registration.sms.challenge_body', ['%challenge%' => $challenge]);

        $smsCommand = new SendSmsCommand();
        $smsCommand->recipient = $command->recipient;
        $smsCommand->originator = $this->originator;
        $smsCommand->body = $body;
        $smsCommand->identity = $command->identity;
        $smsCommand->institution = $command->institution;

        return $this->smsService->sendSms($smsCommand);
    }

    /**
     * @param VerifySmsChallengeCommand $challengeCommand
     * @return ProofOfPossessionResult
     */
    public function provePossession(VerifySmsChallengeCommand $challengeCommand)
    {
        $respondResult = $this->challengeHandler->match($challengeCommand->challenge);

        if ($respondResult->hasChallengeExpired()) {
            return new ProofOfPossessionResult(ProofOfPossessionResult::STATUS_CHALLENGE_EXPIRED);
        } elseif (!$respondResult->didResponseMatch()) {
            return new ProofOfPossessionResult(ProofOfPossessionResult::STATUS_INCORRECT_CHALLENGE);
        }

        $command = new ProvePhonePossessionCommand();
        $command->identityId = $challengeCommand->identity;
        $command->secondFactorId = Uuid::generate();
        $command->phoneNumber = $respondResult->getPhoneNumber();

        $result = $this->commandService->execute($command);

        return new ProofOfPossessionResult(
            ProofOfPossessionResult::STATUS_CHALLENGE_OK,
            $result->isSuccessful() ? $command->secondFactorId : null
        );
    }
}
