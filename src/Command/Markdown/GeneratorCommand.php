<?php
/**
 * Copyright (c) Pierre-Henry Soria <hi@ph7.me>
 * MIT License - https://opensource.org/licenses/MIT
 */

declare(strict_types=1);

namespace PH7\PhpReadmeGeneratorFile\Command\Markdown;

use Nadar\PhpComposerReader\ComposerReader;
use PH7\PhpReadmeGeneratorFile\Command\Exception\EmptyFieldException;
use PH7\PhpReadmeGeneratorFile\Command\Exception\InvalidInputException;
use PH7\PhpReadmeGeneratorFile\DefaultValue;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\HelperInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Style\SymfonyStyle;

class GeneratorCommand extends Command
{
    private const COMPOSER_FILE = 'composer.json';

    private array $composerData;

    public function __construct()
    {
        parent::__construct();

        $this->composerData = $this->getDefaultValues();
    }

    private function getDefaultValues(): array
    {
        $reader = new ComposerReader(ROOT_DIR . DIRECTORY_SEPARATOR . self::COMPOSER_FILE);

        try {
            return $reader->getContent();
        } catch (Exception $e) {
            return [];
        }
    }

    protected function configure(): void
    {
        $this->setName('markdown:generate');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $helper = $this->getHelper('question');
        $io = new SymfonyStyle($input, $output);

        $data = $this->treatFields($input, $output);

        if (is_array($data)) {
            if ($this->finalConfirmation($helper, $input, $output)) {
                $path = $helper->ask($input, $output, $this->promptDestinationFile());
                $path = $this->getValidPath($path);
                $filename = $this->getFilename();

                if (is_dir($path)) {
                    $fileBuilder = new Builder($data);

                    $fullPath = $path . DIRECTORY_SEPARATOR . $filename;
                    $fileBuilder->save($fullPath);

                    $output->writeln(
                        $io->success(sprintf('File successfully saved at: %s', $fullPath))
                    );

                    return Command::SUCCESS;
                } else {
                    $output->writeln(
                        $io->error(sprintf('Oops. The path "%s" doesn\'t exist.', $path))
                    );

                    return Command::INVALID;
                }
            }
        }

        return Command::FAILURE;
    }

    private function treatFields(InputInterface $input, OutputInterface $output): array|int
    {
        $helper = $this->getHelper('question');
        $io = new SymfonyStyle($input, $output);

        try {
            $name = $this->promptName($io);
            $heading = $this->promptHeading($io);
            $description = $this->promptDescription($io);
            $requirements = $this->promptRequirements($io);
            $author = $this->promptAuthor($io);
            $email = $this->promptEmail($io);
            $webpage = $this->promptHomepageUrl($io);
            $githubUsername = $this->promptGithub($io);
            $license = $this->promptLicense($helper, $input, $output);

            return [
                'name' => $name,
                'heading' => $heading,
                'description' => $description,
                'requirements' => $requirements,
                'author' => $author,
                'email' => $email,
                'webpage' => $webpage,
                'github' => $githubUsername,
                'license' => $license
            ];
        } catch (EmptyFieldException $e) {
            $io->warning($e->getMessage());

            return Command::INVALID;
        } catch (InvalidInputException $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        }
    }

    private function promptName(SymfonyStyle $io): string
    {
        $name = $io->ask('Project Name', $this->getDefaultPackageName());

        if (!$this->isFieldFilled($name)) {
            throw new EmptyFieldException('Mention a name for your project 😺');
        }

        return $name;
    }

    private function promptHeading(SymfonyStyle $io): string
    {
        $heading = $io->ask('Project Heading/Summary');

        if (!$this->isFieldFilled($heading)) {
            throw new EmptyFieldException('Mention the README heading or a small summary.');
        }

        return $heading;
    }

    private function promptDescription(SymfonyStyle $io): string
    {
        $description = $io->ask('Project Description', $this->composerData['description']);

        if (!$this->isFieldFilled($description)) {
            throw new EmptyFieldException('Describe a bit your project.');
        }

        return $description;
    }

    private function promptRequirements(SymfonyStyle $io): string
    {
        $phpRequirement = !empty($this->composerData['require']['php']) ? sprintf('* PHP %s', $this->composerData['require']['php']) : null;
        $requirements = $io->ask('Requirements / Installation?', $phpRequirement);

        if (!$this->isFieldFilled($requirements)) {
            throw new EmptyFieldException('What are the requirements/Installation steps for this project?');
        }

        return $requirements;
    }

    private function promptAuthor(SymfonyStyle $io): string
    {
        $authorName = !empty($this->composerData['authors'][0]['name']) ? $this->composerData['authors'][0]['name'] : DefaultValue::AUTHOR;
        $authorName = $io->ask('Author Name', $authorName);

        if (!$this->isFieldFilled($authorName)) {
            throw new EmptyFieldException('Author name is required.');
        }

        return $authorName;
    }

    private function promptEmail(SymfonyStyle $io): string
    {
        $email = !empty($this->composerData['authors'][0]['email']) ? $this->composerData['authors'][0]['email'] : DefaultValue::EMAIL;
        $email = $io->ask('Valid Author Email (will also be used for your gravatar)', $email);

        if (!$this->isFieldFilled($email)) {
            throw new EmptyFieldException('Author email is required.');
        }


        if (!$this->isValidEmail($email)) {
            throw new InvalidInputException('Please mention a valid email 😀');
        }

        return $email;
    }

    private function promptHomepageUrl(SymfonyStyle $io): string
    {
        $personalHomepage = !empty($this->composerData['authors'][0]['homepage']) ? $this->composerData['authors'][0]['homepage'] : $this->composerData['homepage'];

        $webpage = $io->ask('Valid Author Webpage (e.g. https://pierre.com)', $personalHomepage);


        if (!$this->isFieldFilled($webpage)) {
            throw new EmptyFieldException('Can you mention your homepage (.e.g website, GitHub profile, etc)?');
        }

        if (!$this->isValidUrl($webpage)) {
            throw new InvalidInputException('Please mention a valid website URL 😄');
        }

        return $webpage;
    }

    private function isFieldFilled(?string $string): bool
    {
        return !empty($string) && strlen($string) > 0;
    }

    private function isValidEmail(string $email): bool
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    private function isValidUrl(string $url): bool
    {
        return filter_var($url, FILTER_VALIDATE_URL) !== false;
    }

    private function promptGithub(SymfonyStyle $io): string
    {
        $github = $io->ask('GitHub Username (github.com/<username>)', DefaultValue::GITHUB);

        if (!$this->isFieldFilled($github)) {
            throw new EmptyFieldException('GitHub nickname is required.');
        }

        return $github;
    }

    private function promptLicense(HelperInterface $helper, InputInterface $input, OutputInterface $output): string
    {
        $defaultLicense = !empty($this->composerData['license']) ? $this->composerData['license'] : DefaultValue::LICENSE_CODE;

        $question = new ChoiceQuestion(
            sprintf('License [%s]', $defaultLicense),
            License::CODES,
            DefaultValue::LICENSE_CODE
        );

        $question->setErrorMessage('Select a valid license type 🤠');

        $license = $helper->ask($input, $output, $question);

        if (!$this->isFieldFilled($license)) {
            throw new EmptyFieldException('License type is required.');
        }

        return $license;
    }

    private function finalConfirmation(HelperInterface $helper, InputInterface $input, OutputInterface $output): bool
    {
        $question = new ConfirmationQuestion('Are you happy to generate the README? [y/m]', true);

        return (bool)$helper->ask($input, $output, $question);
    }

    private function promptDestinationFile(): Question
    {
        return new Question(
            sprintf('Destination Path [%s]', DefaultValue::DESTINATION_FILE),
            DefaultValue::DESTINATION_FILE
        );
    }

    private function getDefaultPackageName(): string
    {
        $packageName = explode('/', $this->composerData['name']);
        $packageName = str_replace('-', ' ', $packageName[1]);

        return ucwords($packageName);
    }

    private function getValidPath(?string $path): string
    {
        return is_string($path) && strlen($path) > 2 ? realpath($path) : DefaultValue::DESTINATION_FILE;
    }

    private function getFilename(): string
    {
        return sprintf('README-%s.md', date('Y-m-d H:i'));
    }
}
