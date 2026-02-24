import sys

def main():
    with open('index.php', 'r') as f:
        lines = f.readlines()

    end_php_idx = -1
    for i in range(len(lines)):
        if '?>' in lines[i] and i > 400:
            end_php_idx = i
            break

    if end_php_idx == -1:
        print("Could not find ?> block")
        sys.exit(1)

    php_content = "".join(lines[:end_php_idx+1])

    with open('index_html.php', 'r') as f:
        html_content = f.read()

    # The first 3 lines are the PHP tag used for syntax highlighting in the AI
    # <?php
    # // HTML PORTION ONLY
    # ?>
    if html_content.startswith('<?php'):
        html_content = html_content.split('?>\n', 1)[1]

    with open('index.php', 'w') as f:
        f.write(php_content + '\n' + html_content)

    print("Success merging index.php")

if __name__ == '__main__':
    main()
