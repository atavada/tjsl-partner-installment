export default {
	extends: ["@commitlint/config-conventional"],
	rules: {
		"type-enum": [
			2,
			"always",
			["feat", "fix", "docs", "refactor", "style", "test", "chore", "content"],
		],
		"subject-case": [0],
		"header-max-length": [2, "always", 120],
	},
};
