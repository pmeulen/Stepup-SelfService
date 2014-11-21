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

namespace Surfnet\StepupSelfService\SelfServiceBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;

class VerifyEmailType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->add('verificationCode', 'text', [
            'label' => 'ss.form.ss_verify_email.text.verification_code',
            'required' => true,
            'attr' => array(
                'autofocus' => true,
                'autocomplete' => 'off',
            )
        ]);
        $builder->add('verifyEmail', 'submit', [
            'label' => 'ss.form.ss_verify_email.button.verify_email',
            'attr' => [ 'class' => 'btn btn-primary' ],
        ]);
    }

    public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
        $resolver->setDefaults([
            'data_class' => 'Surfnet\StepupSelfService\SelfServiceBundle\Identity\Command\VerifyEmailCommand',
        ]);
    }

    public function getName()
    {
        return 'ss_verify_email';
    }
}
