package matching

// TrieNode represents a node in the domain matching trie.
type TrieNode struct {
	children map[string]*TrieNode
	isEnd    bool
}

// NewTrie creates a new Trie.
func NewTrie() *Trie {
	return &Trie{
		root: &TrieNode{
			children: make(map[string]*TrieNode),
		},
	}
}

// Trie is a tree structure for efficient domain matching.
type Trie struct {
	root *TrieNode
}

// Insert inserts a reversed domain into the trie.
func (t *Trie) Insert(reversed string) {
	node := t.root
	parts := stringsSplit(reversed, ".")

	for _, part := range parts {
		if node.children[part] == nil {
			node.children[part] = &TrieNode{
				children: make(map[string]*TrieNode),
			}
		}
		node = node.children[part]
	}
	node.isEnd = true
}

// Search checks if a reversed domain matches any entry in the trie.
// Supports wildcard matching: if a node has a "*" child, it matches any subdomain.
func (t *Trie) Search(reversed string) bool {
	node := t.root
	parts := stringsSplit(reversed, ".")

	return t.searchRecursive(node, parts, 0)
}

func (t *Trie) searchRecursive(node *TrieNode, parts []string, idx int) bool {
	if node == nil {
		return false
	}

	// If this node is a terminal, the wildcard matches all remaining subdomains.
	// Trie only stores wildcard entries (exact entries use Go maps).
	if node.isEnd {
		return true
	}

	// If we've matched all parts, check if this is a terminal node
	if idx >= len(parts) {
		return node.isEnd
	}

	// Try exact match
	part := parts[idx]
	if child, ok := node.children[part]; ok {
		if t.searchRecursive(child, parts, idx+1) {
			return true
		}
	}

	// Try wildcard match
	if child, ok := node.children["*"]; ok {
		if t.searchRecursive(child, parts, idx+1) {
			return true
		}
	}

	return false
}

// stringsSplit is a helper to split strings without importing strings.
func stringsSplit(s, sep string) []string {
	var result []string
	start := 0
	for i := 0; i < len(s); i++ {
		if i+len(sep) <= len(s) && s[i:i+len(sep)] == sep {
			result = append(result, s[start:i])
			start = i + len(sep)
		}
	}
	result = append(result, s[start:])
	return result
}
