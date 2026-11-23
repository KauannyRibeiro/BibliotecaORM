#!/usr/bin/env python3
"""Gera docs/relatorio_tecnico.pdf (2-4 páginas). Requer: pip install reportlab
Se docs/evidencias.txt existir (php demo/demo.php | tee docs/evidencias.txt), a saída real é incorporada."""
import os
from reportlab.lib.pagesizes import A4
from reportlab.lib.units import cm
from reportlab.lib import colors
from reportlab.lib.styles import ParagraphStyle
from reportlab.platypus import (SimpleDocTemplate, Paragraph, Spacer, Table, TableStyle,
                                Preformatted, KeepTogether)
from reportlab.graphics.shapes import Drawing, Rect, String, Line, Polygon

AQUI = os.path.dirname(os.path.abspath(__file__))
SAIDA = os.path.join(AQUI, "relatorio_tecnico.pdf")
EVID = os.path.join(AQUI, "evidencias.txt")

# ------------------------------------------------------------------ estilos
base = ParagraphStyle("base", fontName="Helvetica", fontSize=9, leading=11.8)
h1 = ParagraphStyle("h1", parent=base, fontName="Helvetica-Bold", fontSize=11.5, leading=14,
                    spaceBefore=8, spaceAfter=3, textColor=colors.HexColor("#1F3A5F"))
titulo = ParagraphStyle("t", parent=base, fontName="Helvetica-Bold", fontSize=15, leading=18, spaceAfter=2)
sub = ParagraphStyle("s", parent=base, fontSize=9, textColor=colors.HexColor("#555555"), spaceAfter=4)
item = ParagraphStyle("i", parent=base, leftIndent=11, bulletIndent=1, spaceAfter=1.5)
cel = ParagraphStyle("c", parent=base, fontSize=7.8, leading=9.6)
celb = ParagraphStyle("cb", parent=cel, fontName="Helvetica-Bold")
aviso = ParagraphStyle("a", parent=base, fontSize=8.4, leading=10.5, backColor=colors.HexColor("#FFF4D6"),
                       borderColor=colors.HexColor("#E0B84C"), borderWidth=0.6, borderPadding=5)

def P(t, s=base): return Paragraph(t, s)
def B(t): return Paragraph(t, item, bulletText="\u2022")

# ------------------------------------------------------------------ diagrama de classes
def diagrama():
    d = Drawing(515, 436)
    cinza, azul = colors.HexColor("#F3F6FA"), colors.HexColor("#DCE7F5")

    def caixa(x, topo, w, titulo_, linhas, estereo=None, cor=cinza):
        alt = 6 + (10 if estereo else 0) + 12 + len(linhas) * 9.4 + 4
        y = topo - alt
        d.add(Rect(x, y, w, alt, fillColor=cor, strokeColor=colors.HexColor("#33475B"), strokeWidth=0.8))
        cy = topo - 9
        if estereo:
            d.add(String(x + w / 2, cy, estereo, fontName="Helvetica-Oblique", fontSize=7, textAnchor="middle"))
            cy -= 10
        d.add(String(x + w / 2, cy - 2, titulo_, fontName="Helvetica-Bold", fontSize=8.6, textAnchor="middle"))
        ysep = cy - 6
        d.add(Line(x, ysep, x + w, ysep, strokeColor=colors.HexColor("#33475B"), strokeWidth=0.5))
        ty = ysep - 9
        for l in linhas:
            d.add(String(x + 5, ty, l, fontName="Helvetica", fontSize=6.8))
            ty -= 9.4
        return y  # base da caixa

    def tri_cima(x, ponta, cheio=False):
        d.add(Polygon([x, ponta, x - 5, ponta - 9, x + 5, ponta - 9],
                      fillColor=colors.black if cheio else colors.white, strokeColor=colors.black, strokeWidth=0.8))

    def tracejada(x1, y1, x2, y2):
        d.add(Line(x1, y1, x2, y2, strokeColor=colors.black, strokeWidth=0.8, strokeDashArray=[3, 2]))

    def seta_baixo(x, ponta):
        d.add(Line(x, ponta, x - 3.5, ponta + 7, strokeColor=colors.black, strokeWidth=0.8))
        d.add(Line(x, ponta, x + 3.5, ponta + 7, strokeColor=colors.black, strokeWidth=0.8))

    yi = caixa(150, 430, 215, "RepositoryInterface",
               ["+buscar(int): ?object", "+listar(): array", "+salvar(object): object", "+excluir(int): bool"],
               "<<interface>>", azul)
    ya = caixa(95, 340, 325, "AbstractRepository",
               ["#pdo: PDO",
                "+buscar(id) / +listar(ordenarPor, direcao) / +salvar(e) / +excluir(id)",
                "#transacao(fn): mixed    #atribuirId(e, ?id)    -ident(nome)",
                "#hidratarLote(linhas)    #persistirRelacionamentos(e)",
                "#removerRelacionamentos(id)   (ganchos opcionais)",
                "#tabela()*   #colunas()*   #classeEntidade()*",
                "+hidratar(linha)*   #extrair(e)*"],
               "<<abstract>>", azul)
    yl = caixa(10, 215, 235, "LivroRepository",
               ["-TABELA_VINCULO = 'livros_autores'", "+buscarPorIsbn(isbn): ?Livro",
                "#hidratarLote(): carrega autores em lote", "#persistirRelacionamentos(livro)",
                "#removerRelacionamentos(id)"])
    yu = caixa(270, 215, 235, "AutorRepository",
               ["+TABELA = 'autores'", "+hidratar(linha): Autor", "#extrair(autor): array", "#colunas(): [nome, nacionalidade]"])
    yli = caixa(10, 112, 190, "Livro",
                ["-id: ?int  (sem setId)", "-titulo, -isbn, -anoPublicacao", "-autores: list<Autor>",
                 "+adicionarAutor(a) / +removerAutor(a)", "+getAutores(): list<Autor>"])
    yau = caixa(315, 112, 190, "Autor", ["-id: ?int  (sem setId)", "-nome: string", "-nacionalidade: ?string"])

    # realização (tracejada + triângulo vazado) Abstract -> Interface
    tracejada(257, 340, 257, yi - 9)
    tri_cima(257, yi)
    # herança (linha cheia + triângulo vazado) repos -> Abstract
    for x in (180, 375):
        d.add(Line(x, 215, x, ya - 9, strokeColor=colors.black, strokeWidth=0.8))
        tri_cima(x, ya)
    # dependência repo -> entidade
    for x in (105, 410):
        tracejada(x, yl if x == 105 else yu, x, 112)
        seta_baixo(x, 112)
    # LivroRepository usa AutorRepository
    tracejada(245, 170, 270, 170)
    d.add(String(257.5, 174, "usa", fontName="Helvetica-Oblique", fontSize=6.2, textAnchor="middle"))
    # associação N:N
    ya_assoc = 112 - 40
    d.add(Line(200, ya_assoc, 315, ya_assoc, strokeColor=colors.black, strokeWidth=0.9))
    d.add(String(257.5, ya_assoc + 3, "livros_autores", fontName="Helvetica-Oblique", fontSize=7, textAnchor="middle"))
    d.add(String(204, ya_assoc - 8, "*", fontName="Helvetica-Bold", fontSize=9))
    d.add(String(306, ya_assoc - 8, "*", fontName="Helvetica-Bold", fontSize=9))
    d.add(String(257.5, 6, "Domínio (Livro, Autor) não conhece PDO/SQL; a infraestrutura depende do domínio, nunca o contrário.",
                 fontName="Helvetica-Oblique", fontSize=6.8, textAnchor="middle"))
    return d

# ------------------------------------------------------------------ conteúdo
story = []
story += [P("BibliotecaORM — Relatório Técnico", titulo),
          P("Programação II · Mapeamento objeto-relacional em PHP/PDO (sem ORM de terceiros) · "
            "Aluna: Kauanny Ribeiro da Silva · Repositório: ______________________________", sub)]

story += [P("1. Visão geral", h1),
          P("A <b>BibliotecaORM</b> persiste <b>Livro</b> e <b>Autor</b> em MySQL (banco <i>biblioteca_orm</i>; tabelas "
            "<i>livros</i>, <i>autores</i> e <i>livros_autores</i>) por meio de uma camada própria de mapeamento: entidades de domínio "
            "encapsuladas, um contrato (<i>RepositoryInterface</i>), um repositório abstrato com o comportamento comum e dois "
            "repositórios concretos que apenas especializam tabela, mapeamento e hidratação. Toda a persistência usa PDO com "
            "<i>prepared statements</i> reais e transações.")]

story += [P("2. Diagrama de classes", h1), diagrama()]

story += [P("3. Decisões arquiteturais", h1),
          B("<b>Separação domínio/infraestrutura.</b> <i>Domain</i> não importa PDO nem conhece SQL; <i>Infrastructure</i> contém "
            "conexão, configuração e repositórios; <i>Contract</i> define a interface. O código consumidor depende só do contrato."),
          B("<b>Identidade imutável.</b> As entidades não têm <i>setId()</i> e suas propriedades são privadas. O id gerado pelo MySQL é "
            "gravado por <i>AbstractRepository::atribuirId()</i>, que executa um <i>Closure::call()</i> no escopo da própria entidade; "
            "assim só a infraestrutura altera o identificador. Em caso de rollback, o id é revertido para <i>null</i>."),
          B("<b>Template Method.</b> <i>AbstractRepository</i> implementa buscar, listar, salvar (INSERT/UPDATE conforme o id), excluir e "
            "<i>transacao()</i>. As subclasses fornecem <i>tabela(), colunas(), classeEntidade(), hidratar()</i> e <i>extrair()</i>, e "
            "ganchos opcionais para relacionamentos. Os tipos de retorno são estreitados (covariância) em <i>buscar()</i>."),
          B("<b>Polimorfismo.</b> <i>demonstrarContrato(RepositoryInterface, object)</i> executa salvar, buscar, listar e excluir "
            "sem <i>if</i>/<i>instanceof</i>; <i>listar()</i> aceita parâmetros opcionais extras sem quebrar o contrato."),
          B("<b>Hidratação e N:N.</b> Linhas viram objetos com <i>casts</i> explícitos (int, string, null). Os autores de vários livros são "
            "carregados com <b>uma</b> consulta (<i>JOIN ... IN (?,?,..)</i>, evitando N+1) e um mapa de identidade local garante que o "
            "mesmo autor seja a mesma instância no carregamento. Ao salvar, os vínculos são sincronizados na mesma transação do livro."),
          B("<b>Segurança.</b> <i>EMULATE_PREPARES=false</i>; valores sempre por <i>bind</i>; identificadores SQL vêm das subclasses e passam por "
            "<i>ident()</i> (regex <i>[A-Za-z_][A-Za-z0-9_]*</i>); <i>ORDER BY</i> aceita só colunas da lista branca e ASC/DESC; credenciais "
            "via variáveis de ambiente/<i>.env</i> (ignorado pelo Git)."),
          B("<b>Transações e FKs.</b> <i>salvar()</i> e <i>excluir()</i> são atômicos (participam de transação já aberta, se houver). "
            "Ambas as FKs de <i>livros_autores</i> usam <i>ON DELETE RESTRICT</i>: autor vinculado não pode ser apagado, e o livro é apagado "
            "depois da remoção explícita dos vínculos, na mesma transação.")]

# ------------------------------------------------------------------ evidências
linhas = [
    ("1", "Cadastro de Livro e Autor; ids gerados", "Seção 1", "id <i>null</i> antes do INSERT e inteiro positivo depois (AUTO_INCREMENT)."),
    ("2", "Busca por ID e reconstrução", "Seção 2", "Objeto novo com mesmo id; tipos int/string preservados; id inexistente retorna <i>null</i>."),
    ("3", "Atualização e nova consulta", "Seção 3", "Título/ano alterados e confirmados por novo SELECT."),
    ("4", "Dois autores em um livro; coleção", "Seção 4", "Duplicata ignorada; 2 autores recuperados; autor repetido = 1 instância."),
    ("5", "Polimorfismo", "Seção 5", "Mesma função com LivroRepository e AutorRepository."),
    ("6", "Erro em transação e rollback", "Seção 6", "FK 1452 no vínculo; livro não persiste; id volta a <i>null</i>."),
    ("7", "Exclusão e restrições de FK", "Seção 7", "Autor vinculado bloqueado (1451); livros removidos; depois autores."),
]
dados = [[P("#", celb), P("Evidência", celb), P("Demo", celb), P("O que a saída comprova", celb)]]
for n, e, s, o in linhas:
    dados.append([P(n, cel), P(e, cel), P(s, cel), P(o, cel)])
tab = Table(dados, colWidths=[0.7 * cm, 4.6 * cm, 1.7 * cm, 10.2 * cm], repeatRows=1)
tab.setStyle(TableStyle([("BACKGROUND", (0, 0), (-1, 0), colors.HexColor("#DCE7F5")),
                         ("GRID", (0, 0), (-1, -1), 0.4, colors.HexColor("#8A99AD")),
                         ("VALIGN", (0, 0), (-1, -1), "TOP"),
                         ("TOPPADDING", (0, 0), (-1, -1), 2), ("BOTTOMPADDING", (0, 0), (-1, -1), 2)]))

story += [P("4. Evidências de execução", h1),
          P("Reprodução: <font face='Courier'>php demo/demo.php | tee docs/evidencias.txt</font> (banco criado com "
            "<font face='Courier'>sql/schema.sql</font>). Testes: <font face='Courier'>php tests/run_tests.php</font>.", base),
          Spacer(1, 4), tab, Spacer(1, 5)]

if os.path.isfile(EVID):
    with open(EVID, encoding="utf-8") as f:
        texto = [l.rstrip("\n") for l in f.readlines() if l.strip() != ""]
    MAX = 95
    if len(texto) > MAX:
        texto = texto[:MAX] + ["... (saída completa em docs/evidencias.txt)"]
    mono = ParagraphStyle("m", fontName="Courier", fontSize=5.9, leading=6.9)
    story += [P("<b>Saída real do demo</b>", base), Spacer(1, 2),
              Preformatted("\n".join(texto), mono)]
else:
    story += [P("<b>[PENDENTE]</b> Execute o demo no seu ambiente, salve a saída em <font face='Courier'>docs/evidencias.txt</font> e rode "
                "<font face='Courier'>python3 docs/gerar_relatorio.py</font> para que a saída real apareça aqui.", aviso)]

story += [P("5. Limitações conhecidas", h1),
          B("ISBN validado por formato (10/13 dígitos), sem verificação do dígito verificador."),
          B("Sem <i>lazy loading</i>, <i>unit of work</i> ou controle de concorrência otimista; o mapa de identidade vale por carregamento."),
          B("A hidratação reaplica as validações do domínio: dados inválidos inseridos fora do ORM lançam exceção na leitura."),
          B("Testes de integração exigem MySQL ativo; <i>exemplares</i> e <i>ExemplarRepository</i> não foram implementados (opcionais)."),
          B("Requer PHP 8.1+ (<i>mbstring</i>, <i>pdo_mysql</i>); <i>PDO::MYSQL_ATTR_FOUND_ROWS</i> é específico do driver MySQL.")]

def rodape(canvas, doc):
    canvas.saveState()
    canvas.setFont("Helvetica", 7.5)
    canvas.setFillColor(colors.HexColor("#666666"))
    canvas.drawRightString(A4[0] - 2 * cm, 1.1 * cm, f"BibliotecaORM — página {doc.page}")
    canvas.restoreState()

doc = SimpleDocTemplate(SAIDA, pagesize=A4, leftMargin=2 * cm, rightMargin=2 * cm, topMargin=1.7 * cm,
                        bottomMargin=1.7 * cm, title="BibliotecaORM — Relatório Técnico", author="Equipe")
doc.build(story, onFirstPage=rodape, onLaterPages=rodape)
print("Gerado:", SAIDA)
