<?php

namespace App\Controller;

use App\Entity\ContactSimple;
use App\Entity\GestionPage;
use App\Entity\Service;
use App\Form\ContactType;
use Nette\Utils\DateTime;
use App\Repository\ServiceRepository;
use App\Repository\PartenaireRepository;
use App\Repository\DetailServiceRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class AccueilController extends AbstractController
{
    /**
     * @Route("/", name="accueil")
     */
    public function index(
        Request $request,
        DetailServiceRepository $sousService,
        \Swift_Mailer $mailer
    ) {
        $captcha= null;
        $services = $this->getDoctrine()
            ->getRepository(Service::class)
            ->findBy(['visible'=>1]);


        $sousServices = $sousService->findBy(['visible'=>1]);


        $blocsPage = $this->getDoctrine()
            ->getRepository(GestionPage::class)
            ->findBy(['PageAssociee'=> 'Accueil', 'Visible'=> 1]);

        $client = new ContactSimple();
        $form = $this->createForm(ContactType::class, $client);
        $form->handleRequest($request);


        if ($form->isSubmitted() && $form->isValid()) {
            //recaptcha
            if (isset($_POST['g-recaptcha-response'])) {
                $captcha = $_POST['g-recaptcha-response'];
            }
            if (!$captcha) {
                $this->addFlash(
                    'danger',
                    'Veuillez verifier le formulaire captcha'
                );
                return $this->redirect($request->getUri());
            }
            $secretKey = "6Lfm2KwUAAAAABOwiaCycthCaEIt68JdbJYNZc8R";
            $ip = $_SERVER['REMOTE_ADDR'];
            // post request to server
            $url = 'https://www.google.com/recaptcha/api/siteverify?secret=' .
                urlencode($secretKey) . '&response=' . urlencode($captcha);

            $response = file_get_contents($url);
            $responseKeys = json_decode($response, true);
            // should return JSON with success as true
            if ($responseKeys["success"]) {
                $entityManager = $this->getDoctrine()->getManager();
                $client->setDateMessage(new DateTime());

                $tete = $client->getNom() . ' ' . $client->getPrenom();
                $message = (new \Swift_Message())
                    ->setSubject($tete)
                    ->setFrom($this->getParameter('mailer_from'))
                    ->setTo('yves@samuel.lu')
                    ->setBody(
                        $this->renderView(
                            'Email/notificationRenseignement.html.twig',
                            ['client' => $client]
                        ),
                        'text/html'
                    );

                $mailer->send($message);

                $this->addFlash(
                    'notice',
                    'Votre message a bien été envoyé !'
                );

                $entityManager->persist($client);
                $entityManager->flush();

                return $this->redirect($request->getUri());
            } else {
                $this->addFlash(
                    'danger',
                    'Veuillez verifier le formulaire captcha'
                );
                return $this->redirect($request->getUri());
            }
        }
        return $this->render('accueil/index.html.twig', [
            'services' => $services,
            'sousServices' => $sousServices,
            'form' => $form->createView(),
            'blocs' => $blocsPage,
        ]);
    }


    /**
     * @Route("/accueil/{id}", name="accueil_service")
     */
    public function detail(DetailServiceRepository $detail_service, Service $service)
    {
        $detail_service = $detail_service->findBy(['service' => $service->getId()]);

        return $this->render('services/details.html.twig', [
            'controller_name' => 'ServicesController',
            'details' => $detail_service,
            'service' => $service
        ]);
    }
}
