<?php

namespace App\Entity;

use App\Repository\PacientRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PacientRepository::class)]
#[ORM\Table(name: "pacienti")]
class Pacient
{
    public function __construct()
    {
        $this->consultatii = new ArrayCollection();
        $this->programari = new ArrayCollection();
        $this->facturi = new ArrayCollection();
    }

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: "integer")]
    private ?int $id = null;

    #[ORM\Column(type: "string", length: 50, nullable: false)]
    private string $nume;

    #[ORM\Column(type: "string", length: 50, nullable: false)]
    private string $prenume;

    #[ORM\Column(type: "string", length: 13, unique: true, nullable: false)]
    private string $cnp;

    #[ORM\Column(type: "string", length: 20)]
    private string $telefon;

    #[ORM\Column(type: "string", length: 20, nullable: true)]
    private ?string $telefon2 = null;

    #[ORM\Column(type: "string", length: 50, nullable: true)]
    private ?string $email = null;

    #[ORM\Column(type: "string", length: 250, nullable: false)]
    private string $adresa;

    #[ORM\Column(type: "string", length: 50, nullable: true)]
    private ?string $judet = null;

    #[ORM\Column(type: "string", length: 50, nullable: true)]
    private ?string $localitate = null;

    #[ORM\Column(type: "string", length: 30, nullable: false)]
    private string $tara;

    #[ORM\Column(type: "string", length: 50, nullable: true)]
    private ?string $locMunca = null;

    #[ORM\Column(type: "string", length: 50, nullable: true)]
    private ?string $ocupatie = null;

    #[ORM\Column(type: "datetime", nullable: true)]
    private ?\DateTimeInterface $dataInreg = null;

    #[ORM\Column(type: "boolean", nullable: false, options: ["default" => "0", "comment" => "0-nesters,1-sters"])]
    private bool $sters = false;

    #[ORM\Column(type: "string", length: 500, nullable: true)]
    private ?string $observatii = null;

    #[ORM\Column(type: "smallint", nullable: false, options:
        ["default" => "0", "comment" => "0-necasatorit,1-casatorit,2-divortat,3-vaduv"])]
    private int $stareCivila = 0;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: "adaugat_de", referencedColumnName: "id", nullable: true)]
    private $adaugatDe = null;

    #[ORM\OneToMany(targetEntity: Consultatie::class, mappedBy: "pacient")]
    private Collection $consultatii;

    #[ORM\OneToMany(targetEntity: Programare::class, mappedBy: 'pacient')]
    private Collection $programari;

    #[ORM\Column(length: 10, nullable: true)]
    private ?string $ci = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $ciEliberat = null;

    /**
     * @var Collection<int, Factura>
     */
    #[ORM\OneToMany(mappedBy: 'pacient', targetEntity: Factura::class)]
    private Collection $facturi;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNume(): ?string
    {
        return $this->nume;
    }

    public function setNume(string $nume): self
    {
        $this->nume = $nume;

        return $this;
    }

    public function getPrenume(): ?string
    {
        return $this->prenume;
    }

    public function setPrenume(string $prenume): self
    {
        $this->prenume = $prenume;

        return $this;
    }

    public function getCnp(): ?string
    {
        return $this->cnp;
    }

    public function setCnp(string $cnp): self
    {
        $this->cnp = $cnp;

        return $this;
    }

    public function getTelefon(): ?string
    {
        return $this->telefon;
    }

    public function setTelefon(string $telefon): self
    {
        $this->telefon = $telefon;

        return $this;
    }

    public function getAdresa(): ?string
    {
        return $this->adresa;
    }

    public function setAdresa(string $adresa): self
    {
        $this->adresa = $adresa;

        return $this;
    }

    public function getDataInreg(): ?\DateTimeInterface
    {
        return $this->dataInreg;
    }

    public function setDataInreg(?\DateTimeInterface $dataInreg): self
    {
        $this->dataInreg = $dataInreg;

        return $this;
    }

    public function getJudet()
    {
        return $this->judet;
    }

    public function setJudet($judet): void
    {
        $this->judet = $judet;
    }

    public function getLocMunca()
    {
        return $this->locMunca;
    }

    public function setLocMunca($locMunca): void
    {
        $this->locMunca = $locMunca;
    }

    public function getOcupatie()
    {
        return $this->ocupatie;
    }

    public function setOcupatie($ocupatie): void
    {
        $this->ocupatie = $ocupatie;
    }

    /**
     * @return mixed
     */
    public function getTara()
    {
        return $this->tara;
    }

    /**
     * @param mixed $tara
     */
    public function setTara($tara): void
    {
        $this->tara = $tara;
    }

    /**
     * @return mixed
     */
    public function getSters()
    {
        return $this->sters;
    }

    /**
     * @param mixed $sters
     */
    public function setSters($sters): void
    {
        $this->sters = $sters;
    }

    /**
     * @return mixed
     */
    public function getTelefon2()
    {
        return $this->telefon2;
    }

    /**
     * @param mixed $telefon2
     */
    public function setTelefon2($telefon2): void
    {
        $this->telefon2 = $telefon2;
    }

    /**
     * @return mixed
     */
    public function getEmail()
    {
        return $this->email;
    }

    /**
     * @param mixed $email
     */
    public function setEmail($email): void
    {
        $this->email = $email;
    }

    /**
     * @return mixed
     */
    public function getLocalitate()
    {
        return $this->localitate;
    }

    /**
     * @param mixed $localitate
     */
    public function setLocalitate($localitate): void
    {
        $this->localitate = $localitate;
    }

    /**
     * @return mixed
     */
    public function getObservatii()
    {
        return $this->observatii;
    }

    /**
     * @param mixed $observatii
     */
    public function setObservatii($observatii): void
    {
        $this->observatii = $observatii;
    }

    /**
     * @return mixed
     */
    public function getStareCivila()
    {
        return $this->stareCivila;
    }

    /**
     * @param mixed $stareCivila
     */
    public function setStareCivila($stareCivila): void
    {
        $this->stareCivila = $stareCivila;
    }

    /**
     * @return mixed
     */
    public function getAdaugatDe()
    {
        return $this->adaugatDe;
    }

    /**
     * @param mixed $adaugatDe
     */
    public function setAdaugatDe($adaugatDe): void
    {
        $this->adaugatDe = $adaugatDe;
    }

    public function getConsultatii(): Collection
    {
        return $this->consultatii;
    }

    public function addConsultatii(Consultatie $consultatie): self
    {
        if (!$this->consultatii->contains($consultatie)) {
            $this->consultatii[] = $consultatie;
            $consultatie->setPacient($this);
        }

        return $this;
    }

    public function removeConsultatii(Consultatie $consultatie): self
    {
        if ($this->consultatii->removeElement($consultatie)) {
            if ($consultatie->getPacient() === $this) {
                $consultatie->setPacient(null);
            }
        }

        return $this;
    }

    public function getProgramari(): Collection
    {
        return $this->programari;
    }

    public function addProgramari(Programare $programare): self
    {
        if (!$this->programari->contains($programare)) {
            $this->programari[] = $programare;
            $programare->setPacient($this);
        }

        return $this;
    }

    public function removeProgramari(Programare $programare): self
    {
        if ($this->programari->removeElement($programare)) {
            if ($programare->getPacient() === $this) {
                $programare->setPacient(null);
            }
        }

        return $this;
    }

    public function getCi(): ?string
    {
        return $this->ci;
    }

    public function setCi(?string $ci): static
    {
        $this->ci = $ci;

        return $this;
    }

    public function getCiEliberat(): ?string
    {
        return $this->ciEliberat;
    }

    public function setCiEliberat(?string $ciEliberat): static
    {
        $this->ciEliberat = $ciEliberat;

        return $this;
    }

    /**
     * @return Collection<int, Factura>
     */
    public function getFacturi(): Collection
    {
        return $this->facturi;
    }

    public function addFacturi(Factura $facturi): static
    {
        if (!$this->facturi->contains($facturi)) {
            $this->facturi->add($facturi);
            $facturi->setPacient($this);
        }

        return $this;
    }

    public function removeFacturi(Factura $facturi): static
    {
        if ($this->facturi->removeElement($facturi)) {
            // set the owning side to null (unless already changed)
            if ($facturi->getPacient() === $this) {
                $facturi->setPacient(null);
            }
        }

        return $this;
    }

    public function setId($id)
    {
        $this->id = $id;

        return $this;
    }
}
