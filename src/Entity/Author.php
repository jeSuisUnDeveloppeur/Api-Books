<?php

namespace App\Entity;

use App\Repository\AuthorRepository;
use ApiPlatform\Metadata\ApiResource;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use JMS\Serializer\Annotation\Groups;
use Hateoas\Configuration\Annotation as Hateoas;
use Symfony\Component\Validator\Constraints as Assert;

#[Hateoas\Relation(
    name: 'self',
    href: new Hateoas\Route(
        name: 'detailAuthor',
        parameters: ['id' => 'expr(object.getId())']
    ),
    exclusion: new Hateoas\Exclusion(groups: ['getAuthors'])
)]
#[Hateoas\Relation(
    name: 'delete',
    href: new Hateoas\Route(
        name: 'deleteAuthor',
        parameters: ['id' => 'expr(object.getId())']
    ),
    exclusion: new Hateoas\Exclusion(groups: ['getAuthors'], excludeIf: 'expr(not is_granted("ROLE_ADMIN"))')
)]
#[Hateoas\Relation(
    name: 'update',
    href: new Hateoas\Route(
        name: 'updateAuthor',
        parameters: ['id' => 'expr(object.getId())']
    ),
    exclusion: new Hateoas\Exclusion(groups: ['getAuthors'], excludeIf: 'expr(not is_granted("ROLE_ADMIN"))')
)]
#[ORM\Entity(repositoryClass: AuthorRepository::class)]
#[ApiResource()]
class Author
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    #[Groups(["getBooks", "getAuthors"])]
    private $id;

    #[ORM\Column(type: 'string', length: 255)]
    #[Groups(["getBooks", "getAuthors"])]
    #[Assert\NotBlank(message: "Le nom de l'auteur est obligatoire")]
    #[Assert\Length(min: 1, max: 255, minMessage: "Le nom de l'auteur doit faire au moins {{ limit }} caractère", maxMessage: "Le nom de l'auteur ne peut pas faire plus de {{ limit }} caractères")]
    private $lastName;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    #[Groups(["getBooks", "getAuthors"])]
    private $firstName;

    #[ORM\OneToMany(mappedBy: 'author', targetEntity: Book::class)]
    #[Groups(["getAuthors"])]
    private $books;

    public function __construct()
    {
        $this->books = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getLastName(): ?string
    {
        return $this->lastName;
    }

    public function setLastName(string $lastName): self
    {
        $this->lastName = $lastName;

        return $this;
    }

    public function getFirstName(): ?string
    {
        return $this->firstName;
    }

    public function setFirstName(?string $firstName): self
    {
        $this->firstName = $firstName;

        return $this;
    }

    /**
     * @return Collection<int, Book>
     */
    public function getBooks(): Collection
    {
        return $this->books;
    }

    public function addBook(Book $book): self
    {
        if (!$this->books->contains($book)) {
            $this->books[] = $book;
            $book->setAuthor($this);
        }

        return $this;
    }

    public function removeBook(Book $book): self
    {
        if ($this->books->removeElement($book)) {
            // set the owning side to null (unless already changed)
            if ($book->getAuthor() === $this) {
                $book->setAuthor(null);
            }
        }

        return $this;
    }
}